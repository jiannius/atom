<?php

use Jiannius\Atom\Actions\GetOptions;
use Jiannius\Atom\Contracts\WebAction;

it('invokes an action from php whether or not it opted in to http', function () {
    expect(app('atom')->action('closed', []))->toBe(['ran' => true]);
});

it('still lets a php caller choose the method', function () {
    expect(app('atom')->action('open', ['method' => 'secret']))->toBe(['ran' => 'secret']);
});

it('throws from php when the action does not exist', function () {
    app('atom')->action('no-such-action', []);
})->throws(Exception::class);

it('falls back to the packaged action when the host app has none', function () {
    // No fixture may define App\Actions\GetOptions in this process, or this is not a
    // fallback at all — the e2e app aliases one in, and must not leak it here.
    expect(class_exists('App\Actions\GetOptions'))->toBeFalse();

    $resolve = new ReflectionMethod(app('atom'), 'resolveAction');

    expect($resolve->invoke(app('atom'), 'get-options'))->toBe(GetOptions::class)
        ->and(app('atom')->action('get-options', ['name' => 'dialcodes']))->toBeArray();
});

it('lets an action opt in by extending one that already did', function () {
    $response = $this->postJson('/atom/action/inherited');

    $response->assertOk();
    expect($response->json('ran'))->toBe('inherited');
});

it('keeps get-options reachable over http so guest-facing selects work', function () {
    expect(GetOptions::class)->toImplement(WebAction::class);

    $response = $this->postJson('/atom/action/get-options', ['name' => 'dialcodes']);

    $response->assertOk();
    expect($response->json())->not->toBeEmpty();
});
