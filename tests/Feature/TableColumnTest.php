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

// The bag used to be merged onto the layout wrapper inside the <th>, so a
// responsive class hid the label but left the header cell standing — the column
// could not be dropped at a breakpoint. table.cell always put it on the <td>.
it('merges the caller attributes onto the th, not the inner wrapper', function () {
    $html = Blade::render('<atom:table.column class="hidden lg:table-cell" data-column="out-date">Out-Date</atom:table.column>');

    expect($html)
        ->toMatch('/<th[^>]*\bhidden lg:table-cell\b/')
        ->toMatch('/<th[^>]*data-column="out-date"/')
        // the component's own layout classes stay on the wrapper
        ->toMatch('/<div[^>]*\bpy-1\.5 px-3\b/');

    // and the caller's class lands once, not on both elements
    expect(substr_count($html, 'lg:table-cell'))->toBe(1);
});

it('keeps the th layout classes when the caller passes none', function () {
    $html = Blade::render('<atom:table.column align="right">Total</atom:table.column>');

    expect($html)
        ->toMatch('/<th[^>]*\btext-right\b/')
        ->toMatch('/<th[^>]*\bsticky top-0\b/')
        ->toMatch('/<div[^>]*\bjustify-end\b/');
});

it('still sorts when the caller also passes attributes', function () {
    $html = Blade::render('<atom:table.column sort="name" class="hidden md:table-cell">Contact</atom:table.column>');

    expect($html)
        ->toMatch('/<th[^>]*\bhidden md:table-cell\b/')
        ->toContain('_table.sort.direction');
});
