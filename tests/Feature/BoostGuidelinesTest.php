<?php

use Illuminate\Support\Facades\Blade;

it('does not compile documented atom tags in the boost guidelines', function () {
    $contents = file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php');

    $compiled = Blade::compileString($contents);

    expect($compiled)->not->toContain('$__componentOriginal');
});

// This file is copied into consuming apps' AI guidelines, so a wrong prop here
// teaches every agent in every app a component API that does not exist — and it
// reinstates itself whenever Boost regenerates, overwriting hand corrections.
it('reaches a remote option set through the options prop, not name', function () {
    $contents = file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php');

    expect($contents)->toContain('<atom:select options="users"')
        ->and($contents)->not->toContain('<atom:select name=')
        ->and($contents)->not->toContain(':callback');
});
