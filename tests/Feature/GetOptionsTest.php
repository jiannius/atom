<?php

use Illuminate\Foundation\Auth\User;
use Jiannius\Atom\Tests\Fixtures\GetOptionsSubclass;

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
