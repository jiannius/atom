<?php

use Illuminate\Support\Facades\Blade;

describe('empty', function () {
    it('sizes the default icon to size-14, not the icon size-5 fallback', function () {
        // Regression: the size class sat on the wrapper div, so icon._wrapper
        // fell back to its size-5 default and rendered a tiny glyph in a big box.
        $html = Blade::render('<atom:empty />');

        expect($html)
            ->toContain('data-atom-empty')
            ->toContain('No Results')
            ->toContain('We could not find anything.')
            ->toContain('size-14')
            ->not->toContain('size-5');
    });

    it('sizes the sm-variant icon to size-10', function () {
        $html = Blade::render('<atom:empty size="sm" />');

        expect($html)
            ->toContain('size-10')
            ->not->toContain('size-5');
    });

    it('renders the subtle variant as a single heading box', function () {
        $html = Blade::render('<atom:empty subtle heading="Nothing here" />');

        expect($html)
            ->toContain('Nothing here')
            ->not->toContain('data-atom-empty');
    });
});
