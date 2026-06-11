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
});
