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
            ->toContain('x-data="rating({ count: 5, half: false, clearable: false, value: 0 })"')
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
            ->toContain('data-atom-rating-readonly')
            ->not->toContain('readonly: ')
            ->not->toContain('data-atom-disabled')
            ->not->toContain('role="slider"')
            ->not->toContain('tabindex');
    });

    it('renders a disabled rating as a slider that is no tab stop, and holds the state in the markup and not in x-data', function () {
        $html = renderBlade('<atom:rating :value="3" disabled/>');

        expect($html)
            ->toContain('data-atom-disabled="self"')
            ->toContain('role="slider"')
            ->toContain('aria-disabled="true"')
            ->toContain(':aria-valuenow="value"')
            ->not->toContain('tabindex')
            ->not->toContain('role="img"')
            ->not->toContain('data-atom-rating-readonly')
            ->toContain('x-data="rating({ count: 5, half: false, clearable: false, value: 3 })"');

        // the wrapper does not carry the prop as an attribute of its own
        expect(preg_match('/<div\s[^>]*\sdisabled[\s=>]/', $html))->toBe(0);
    });

    it('inherits a disabled form, and an own :disabled="false" wins', function () {
        expect(renderBlade('<atom:form disabled><atom:rating/></atom:form>'))
            ->toContain('data-atom-disabled="inherited"')
            ->toContain('aria-disabled="true"')
            ->not->toContain('tabindex');

        expect(renderBlade('<atom:form disabled><atom:rating :disabled="false"/></atom:form>'))
            ->not->toContain('data-atom-disabled')
            ->toContain('tabindex="0"');
    });

    it('lets disabled win over readonly: one is a display value, the other a control that is off', function () {
        expect(renderBlade('<atom:rating readonly disabled/>'))
            ->toContain('role="slider"')
            ->toContain('aria-disabled="true"')
            ->not->toContain('role="img"');
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
