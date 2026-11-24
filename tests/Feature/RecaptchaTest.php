<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Resolve the verifier fresh each call.
 */
function recaptcha()
{
    return app('atom')->recaptcha();
}

describe('verify — fail open (unconfigured, or google cannot answer)', function () {
    // Each test fails the run if verify() throws (the exception propagates);
    // the Http assertion both documents the path and keeps the test non-risky.
    it('allows the submit when no secret is configured', function () {
        config(['services.recaptcha.secret_key' => null]);
        Http::fake();

        recaptcha()->verify('any-token');

        Http::assertNothingSent();
    });

    it('allows the submit when no secret is configured, token or not', function () {
        config(['services.recaptcha.secret_key' => null]);
        Http::fake();

        recaptcha()->verify(null);
        recaptcha()->verify('');
        recaptcha()->verify(['not', 'a', 'string']);

        Http::assertNothingSent();
    });

    it('allows the submit when google is unreachable', function () {
        config(['services.recaptcha.secret_key' => 'secret']);
        Http::fake(['*' => Http::response('', 500)]);

        recaptcha()->verify('token');   // ->throw() raises, gets caught, fails open

        Http::assertSentCount(1);
    });

    it('allows the submit on a config-side error code', function () {
        config(['services.recaptcha.secret_key' => 'secret']);
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']])]);

        recaptcha()->verify('token');

        Http::assertSentCount(1);
    });

    it('allows the submit on a passing score', function () {
        config(['services.recaptcha.secret_key' => 'secret', 'services.recaptcha.min_score' => 0.5]);
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'submit'])]);

        recaptcha()->verify('token', 'submit');

        Http::assertSentCount(1);
    });
});

describe('verify — fail closed', function () {
    beforeEach(fn () => config(['services.recaptcha.secret_key' => 'secret']));

    // A client can leave the token out, or post anything, so a missing one is a failed check, not a skipped one.
    it('throws when no token was sent, without asking google', function ($token) {
        Http::fake();

        expect(fn () => recaptcha()->verify($token))->toThrow(ValidationException::class);

        Http::assertNothingSent();
    })->with([
        'null' => [null],
        'empty' => [''],
        'blank' => ['   '],
        'array' => [['x']],
        'int' => [1],
        'bool' => [true],
    ]);

    it('throws when the token was minted for another hostname', function () {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'hostname' => 'evil.test'])]);

        expect(fn () => recaptcha()->verify('token'))->toThrow(ValidationException::class);
    });

    it('accepts the request hostname, in any case', function () {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'hostname' => strtoupper(request()->getHost())])]);

        recaptcha()->verify('token');

        Http::assertSentCount(1);
    });

    it('does not check the hostname when google sends none', function () {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9])]);

        recaptcha()->verify('token');

        Http::assertSentCount(1);
    });

    it('throws on a bot error code', function () {
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        expect(fn () => recaptcha()->verify('token'))->toThrow(ValidationException::class);
    });

    it('throws when the score is below the threshold', function () {
        config(['services.recaptcha.min_score' => 0.5]);
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.1])]);

        expect(fn () => recaptcha()->verify('token'))->toThrow(ValidationException::class);
    });

    it('honours a per-call min score override', function () {
        config(['services.recaptcha.min_score' => 0.1]);   // config would pass...
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.5])]);

        expect(fn () => recaptcha()->verify('token', null, 0.9))   // ...but the override rejects
            ->toThrow(ValidationException::class);
    });

    it('throws when the returned action does not match', function () {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        expect(fn () => recaptcha()->verify('token', 'register'))->toThrow(ValidationException::class);
    });
});

describe('verifyRecaptcha() on a component', function () {
    $component = fn () => new class extends \Livewire\Component {
        use \Jiannius\Atom\Traits\WithRecaptcha;

        public function create() { $this->verifyRecaptcha(); }

        public function render() { return '<div></div>'; }
    };

    it('rejects a submit that never set the token, and one that posted an array', function () use ($component) {
        config(['services.recaptcha.secret_key' => 'secret']);
        Http::fake();

        \Livewire\Livewire::test($component())->call('create')->assertHasErrors('_recaptcha');
        \Livewire\Livewire::test($component())->set('_recaptcha', ['x'])->call('create')->assertHasErrors('_recaptcha');

        Http::assertNothingSent();
    });

    it('lets a submit through when recaptcha is not configured', function () use ($component) {
        config(['services.recaptcha.secret_key' => null]);

        \Livewire\Livewire::test($component())->call('create')->assertHasNoErrors();
    });
});
