<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('rating', function () {
    it('renders base + fill rows bound through x-modelable', function () {
        $html = renderBlade('<atom:rating wire:model="score" label="Score"/>');

        expect($html)
            ->toContain('data-atom-rating')
            ->toContain('data-atom-rating-base')
            ->toContain('data-atom-rating-fill')
            ->toContain('x-data="rating({ count: 5, half: false, readonly: false, clearable: false, value: 0 })"')
            ->toContain('x-modelable="value"')
            ->toContain('wire:model="score"')
            ->toContain('role="slider"')
            ->toContain('Score');

        // default star drawn once per icon in both rows → 2 * count
        expect(substr_count($html, 'data-atom-rating-icon'))->toBe(10);
    });

    it('honours the count prop', function () {
        $html = renderBlade('<atom:rating :count="3"/>');

        expect($html)->toContain('count: 3');
        expect(substr_count($html, 'data-atom-rating-icon'))->toBe(6);
    });

    it('forwards half, clearable and value into the factory', function () {
        expect(renderBlade('<atom:rating half clearable :value="3.5"/>'))
            ->toContain('half: true')
            ->toContain('clearable: true')
            ->toContain('value: 3.5');
    });

    it('renders a non-interactive image role when readonly', function () {
        $html = renderBlade('<atom:rating :value="4" readonly/>');

        expect($html)
            ->toContain('role="img"')
            ->toContain('readonly: true')
            ->not->toContain('role="slider"')
            ->not->toContain('tabindex');
    });

    it('swaps the default star for a custom icon', function () {
        $html = renderBlade('<atom:rating icon="heart"/>');

        expect($html)
            ->toContain('lucide-heart')
            ->not->toContain('data-atom-rating-icon');

        // the custom glyph is still drawn 2 * count times
        expect(substr_count($html, 'lucide-heart-icon'))->toBe(10);
    });

    it('renders caption and error chrome', function () {
        expect(renderBlade('<atom:rating caption="Tap to rate."/>'))->toContain('Tap to rate.');
        expect(renderBlade('<atom:rating error="Please rate."/>'))->toContain('Please rate.');
    });
});
