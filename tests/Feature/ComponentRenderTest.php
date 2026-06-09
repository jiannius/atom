<?php

use Illuminate\Support\Facades\Blade;

it('renders an atom button with its label and data hook', function () {
    $html = Blade::render('<atom:button>Save</atom:button>');

    expect($html)->toContain('Save')
        ->and($html)->toContain('data-atom-button');
});
