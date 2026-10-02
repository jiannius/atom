<?php

use Illuminate\Foundation\Auth\User;
use Jiannius\Atom\Tests\Fixtures\GetOptionsSubclass;
use Jiannius\Atom\Tests\Fixtures\XssOptions;

it('does not read outside its json directory', function () {
    $response = $this->postJson('/atom/action/get-options', ['name' => '../../composer']);

    $response->assertOk();
    expect($response->json())->toBe([]);
});

it('does not 500 on an unknown option name', function () {
    $response = $this->postJson('/atom/action/get-options', ['name' => 'no-such-list']);

    $response->assertOk();
    expect($response->json())->toBe([]);
});

// testbench's default cache store is `database`, whose table isn't migrated
// here — see the same note in FilterMacroTest.
it('does not cache a declared option set that has no file behind it', function () {
    config()->set('cache.default', 'array');

    (new GetOptionsSubclass())->handle(['name' => 'ghost']);

    expect(data_get(cache('_options'), 'ghost'))->toBeNull();
});

it('still caches an option set it did find', function () {
    config()->set('cache.default', 'array');

    (new GetOptionsSubclass())->handle(['name' => 'colors']);

    expect(data_get(cache('_options'), 'colors'))->not->toBeNull();
});

it('still serves the package option sets to a guest', function () {
    $response = $this->postJson('/atom/action/get-options', ['name' => 'dialcodes']);

    $response->assertOk();
    expect($response->json())->not->toBeEmpty();
});

it('does not invoke a method the request named but the class did not declare', function () {
    $action = new GetOptionsSubclass();

    $options = $action->handle(['name' => 'purge-cache']);

    expect($action->canaryWasCalled)->toBeFalse()
        ->and($options)->toBe([]);
});

it('serves an option set the class declared', function () {
    $options = (new GetOptionsSubclass())->handle(['name' => 'brands']);

    expect($options)->toHaveCount(1)
        ->and(data_get($options, '0.label'))->toBe('Acme');
});

it('lets a guest read an option set declared as guest-readable', function () {
    expect((new GetOptionsSubclass())->authorize(['name' => 'brands']))->toBeTrue();
});

it('lets a guest read the package option sets', function () {
    expect((new GetOptionsSubclass())->authorize(['name' => 'countries']))->toBeTrue();
});

it('refuses a guest an option set declared as auth-only', function () {
    expect((new GetOptionsSubclass())->authorize(['name' => 'contacts']))->toBeFalse();
});

it('allows a signed-in caller an option set declared as auth-only', function () {
    $this->actingAs(new User());

    expect((new GetOptionsSubclass())->authorize(['name' => 'contacts']))->toBeTrue();
});

it('does not 500 when the request omits the name entirely', function () {
    $response = $this->postJson('/atom/action/get-options', []);

    $response->assertOk();
    expect($response->json())->toBe([]);
});

// The browser renders the html key with x-html, so what getOptionHtml() builds
// from an option's label and caption must be text by the time it leaves.
it('escapes a label and caption that hold markup', function () {
    $option = (new GetOptionsSubclass())->getOptionHtml([
        'value' => 1,
        'label' => '<img src=x onerror=alert(1)>Mallory',
        'caption' => '<script>alert(2)</script>',
    ]);

    expect($option['html'])
        ->not->toContain('<img')
        ->not->toContain('<script')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;Mallory')
        ->toContain('&lt;script&gt;alert(2)&lt;/script&gt;');
});

it('escapes an option that also carries an avatar', function () {
    $option = (new GetOptionsSubclass())->getOptionHtml([
        'value' => 1,
        'label' => '<img src=x onerror=alert(1)>',
        'caption' => '<b>x</b>',
        'avatar' => 'https://example.test/a.png',
    ]);

    expect($option['html'])
        ->not->toContain('<img src=x')
        ->not->toContain('<b>x</b>')
        ->toContain('&lt;b&gt;x&lt;/b&gt;');
});

// `avatar` is not an avatar prop: it used to land as a stray attribute on the
// <figure> (the bag is printed now) and an array one threw in trim() on the
// unauthenticated endpoint. The URL has to reach <img src>, scheme-checked.
describe('an option avatar', function () {
    function avatarHtml(mixed $avatar): string
    {
        return (new GetOptionsSubclass())->getOptionHtml(['value' => 1, 'label' => 'Jane', 'avatar' => $avatar])['html'];
    }

    it('renders a string avatar as the image, with no stray avatar attribute', function () {
        $html = avatarHtml('https://example.test/a.png');
        $img = domQuery($html, '//figure//img');

        expect($img)->toHaveCount(1)
            ->and($img[0]->getAttribute('src'))->toBe('https://example.test/a.png')
            ->and(domQuery($html, '//*[@avatar]'))->toHaveCount(0);
    });

    // The avatar used to be handed its label as a slot, which it never prints: a
    // fallback (no or blocked src) was an empty grey box and the <img> had alt="".
    it('names the image after the label', function () {
        $img = domQuery(avatarHtml('https://example.test/a.png'), '//figure//img')[0];

        expect($img->getAttribute('alt'))->toBe('Jane');
    });

    it('shows the label initials when there is no usable image', function (mixed $avatar) {
        $html = avatarHtml($avatar);
        $figure = domQuery($html, '//figure')[0];

        expect(domQuery($html, '//img'))->toHaveCount(0)
            ->and(trim($figure->textContent))->toContain('J');
    })->with([
        'blocked scheme' => ['javascript:alert(1)'],
        'array' => [fn () => ['url' => 'https://example.test/a.png']],
    ]);

    it('does not throw on an array or object avatar, and renders no image', function (mixed $avatar) {
        $html = avatarHtml($avatar);

        expect(domQuery($html, '//figure'))->toHaveCount(1)
            ->and(domQuery($html, '//img'))->toHaveCount(0)
            ->and(domQuery($html, '//*[@avatar]'))->toHaveCount(0);
    })->with([
        'array' => [fn () => ['url' => 'https://example.test/a.png']],
        'object' => [fn () => (object) ['url' => 'https://example.test/a.png']],
    ]);

    it('lets no javascript: avatar reach img src', function () {
        $html = avatarHtml('javascript:alert(1)');

        expect(domQuery($html, '//img'))->toHaveCount(0)
            ->and($html)->not->toContain('javascript:');
    });
});

it('does not double the escape of a plain label', function () {
    $option = (new GetOptionsSubclass())->getOptionHtml(['value' => 1, 'label' => 'Tom & Jerry']);

    expect($option['html'])->toContain('Tom &amp; Jerry')->not->toContain('&amp;amp;');
});

it('leaves the raw label on the option, for search and text rendering', function () {
    $option = (new GetOptionsSubclass())->getOptionHtml(['value' => 1, 'label' => '<b>x</b>']);

    expect($option['label'])->toBe('<b>x</b>');
});

it('passes a host-supplied html key through unchanged', function () {
    $html = '<strong data-trusted>Acme</strong>';

    $option = (new GetOptionsSubclass())->getOptionHtml(['value' => 1, 'label' => '<b>x</b>', 'html' => $html]);

    expect($option['html'])->toBe($html);
});

it('escapes the markup of a whole option set handle() returns', function () {
    $options = (new XssOptions())->handle(['name' => 'hostile-people']);

    expect(data_get($options, '0.html'))
        ->not->toContain('<img')
        ->not->toContain('<script')
        ->toContain('&lt;img src=x onerror=');
    expect(data_get($options, '1.html'))->toContain('Alice &amp; Bob &lt;b&gt;bold&lt;/b&gt;');
});

it('returns a trusted html key from handle() as the host wrote it', function () {
    $options = (new XssOptions())->handle(['name' => 'trusted-html']);

    expect(data_get($options, '0.html'))->toBe('<strong data-trusted>Trusted</strong>');
});
