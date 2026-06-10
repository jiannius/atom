<?php

use Illuminate\Support\Facades\Blade;

it('does not compile documented atom tags in the boost guidelines', function () {
    $contents = file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php');

    $compiled = Blade::compileString($contents);

    expect($compiled)->not->toContain('$__componentOriginal');
});
