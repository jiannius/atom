<?php

use Jiannius\Atom\Atom;

it('exposes a command() fluent with show and close on the Atom singleton', function () {
    $palette = app(Atom::class)->command('my-palette');

    expect($palette->name)->toBe('my-palette');
    expect(method_exists($palette, 'show'))->toBeTrue();
    expect(method_exists($palette, 'close'))->toBeTrue();
});
