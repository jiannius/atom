<?php

use Illuminate\Support\Facades\Blade;

it('sizes the sort indicator to its size-3 box, not the icon default', function () {
    // The indicator wrapper is size-3; the arrow icons must opt into size-3
    // too, otherwise icon._wrapper falls back to its size-5 default and the
    // shrink-0 arrow overflows the box.
    $html = Blade::render('<atom:table.column sort="name">Contact</atom:table.column>');

    expect($html)
        ->toContain('_table.sort.direction')
        ->toContain('data-atom-icon')
        ->not->toContain('size-5');
});

it('omits the sort indicator when the column is not sortable', function () {
    $html = Blade::render('<atom:table.column>Contact</atom:table.column>');

    expect($html)->not->toContain('_table.sort.column');
});
