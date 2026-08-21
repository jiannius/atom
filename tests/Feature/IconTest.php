<?php

describe('icon', function () {
    it('renders an svg in a labelled-by-hook wrapper', function () {
        $html = renderBlade('<atom:icon.close/>');

        expect($html)
            ->toContain('data-atom-icon')
            ->toContain('<svg');
    });

    it('defaults to size-5', function () {
        expect(renderBlade('<atom:icon.add/>'))->toContain('size-5');
    });

    it('drops the default size when a size class is supplied', function () {
        $html = renderBlade('<atom:icon.add class="size-4"/>');

        expect($html)
            ->toContain('size-4')
            ->not->toContain('size-5');
    });

    it('swaps to the solid glyph when variant is solid', function () {
        expect(renderBlade('<atom:icon.home variant="solid"/>'))->toContain('M11.47 3.841');
        expect(renderBlade('<atom:icon.home/>'))->toContain('m2.25 12 8.954');
    });

    it('hides a decorative icon from assistive tech by default', function () {
        expect(renderBlade('<atom:icon.close/>'))->toContain('aria-hidden="true"');
    });

    it('exposes the icon when it carries its own label', function () {
        $html = renderBlade('<atom:icon.close aria-label="Close"/>');

        expect($html)
            ->toContain('aria-label="Close"')
            ->not->toContain('aria-hidden');
    });

    it('exposes the icon when it carries a title or role', function () {
        expect(renderBlade('<atom:icon.close title="Close"/>'))->not->toContain('aria-hidden');
        expect(renderBlade('<atom:icon.close role="img"/>'))->not->toContain('aria-hidden');
    });

    // dropdown used to hardcode fill="#888" plus an inline 14x20 size, which beat
    // the wrapper's sizing classes and ignored the surrounding text colour — so it
    // could not be used as a button suffix like every other glyph in the set.
    it('lets the dropdown glyph inherit colour and size', function () {
        $html = renderBlade('<atom:icon.dropdown class="size-4"/>');

        expect($html)
            ->toContain('fill="currentColor"')
            ->toContain('size-4')
            ->not->toContain('style="width');
    });
});

describe('icon glyphs', function () {
    // Added because the consumer had no bike glyph (so "New Vehicle" and "New
    // Bike" both rendered the car icon), no heavy-equipment glyph (it fell back
    // to settings, a gear, colliding with the nav item using it) and no camera
    // (hand-inlined in the app).
    it('renders the glyphs added for vehicle types and capture', function (string $name, string $marker) {
        $html = renderBlade('<atom:icon.'.$name.'/>');

        expect($html)
            ->toContain('data-atom-icon')
            ->toContain('stroke="currentColor"')
            ->toContain($marker);
    })->with([
        ['motorcycle', 'lucide-motorbike'],
        ['forklift', 'lucide-forklift'],
        ['camera', 'lucide-camera'],
    ]);

    it('lets the new glyphs inherit colour and size', function (string $name) {
        $html = renderBlade('<atom:icon.'.$name.' class="size-4"/>');

        expect($html)->toContain('size-4')->not->toContain('size-5');
    })->with(['motorcycle', 'forklift', 'camera']);
});
