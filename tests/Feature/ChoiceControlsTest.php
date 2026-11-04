<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('checkbox', function () {
    it('renders a checkbox and applies the name from wire:model', function () {
        // Regression: the component built $merges with the name but rendered
        // {{ $attributes }} instead of {{ $attributes->merge($merges) }}.
        $html = renderBlade('<atom:checkbox wire:model="agree" label="I agree" />');

        expect($html)
            ->toContain('data-atom-checkbox')
            ->toContain('type="checkbox"')
            ->toContain('name="agree"')
            ->toContain('I agree');
    });

    it('keeps the real input operable instead of a fake role=checkbox div', function () {
        // a11y: the input is sr-only (focusable + in the a11y tree, Space
        // toggles natively); the visual swatch is decorative aria-hidden, not
        // a role/tabindex div that no keyboard handler ever wired up.
        $html = renderBlade('<atom:checkbox label="x" />');

        expect($html)
            ->toContain('class="sr-only peer"')
            ->toContain('aria-hidden="true"')
            ->toContain('peer-focus-visible:outline-2')
            ->not->toContain('role="checkbox"')
            ->not->toContain('tabindex');
    });

    it('renders a caption', function () {
        $html = renderBlade('<atom:checkbox label="x" caption="Helper" />');

        expect($html)->toContain('data-atom-caption')->toContain('Helper');
    });
});

describe('radio', function () {
    it('prints a caption under the label inside the same <label>, in a card group too (#161)', function () {
        $html = renderBlade('<atom:radio.group variant="card"><atom:radio name="b" value="y" label="Yearly" caption="RM 500 a year" align="start"/></atom:radio.group>');
        $labels = domQuery($html, '//label[@data-atom-radio]');

        expect($labels)->toHaveCount(1)
            ->and($labels[0]->textContent)->toContain('Yearly')->toContain('RM 500 a year')
            // the dot sits on the first line
            ->and(domQuery($html, '//label[@data-atom-radio]//div[contains(@class, "items-start")]'))->toHaveCount(1)
            // and the panel is the card's
            ->and($html)->toContain('divide-y');
    });

    it('renders a native radio with the decorative swatch hidden from AT', function () {
        $html = renderBlade('<atom:radio label="Option A" />');

        expect($html)
            ->toContain('data-atom-radio')
            ->toContain('type="radio"')
            ->toContain('class="sr-only peer"')
            ->toContain('aria-hidden="true"')
            ->not->toContain('role="checkbox"')
            ->not->toContain('role="radio"')   // native input carries the role
            ->not->toContain('tabindex')
            ->toContain('group-has-[.error]/radio:outline-1')
            ->not->toContain('group-has-[.error]/checkbox');
    });
});

describe('toggle', function () {
    it('renders a checkbox-backed toggle with the name applied', function () {
        $html = renderBlade('<atom:toggle wire:model="enabled" label="Enabled" />');

        expect($html)
            ->toContain('data-atom-toggle')
            ->toContain('type="checkbox"')
            ->toContain('name="enabled"')
            ->toContain('Enabled');
    });

    it('keeps the real input operable instead of a fake role=checkbox div', function () {
        $html = renderBlade('<atom:toggle label="x" />');

        expect($html)
            ->toContain('class="peer sr-only"')
            ->toContain('aria-hidden="true"')
            ->toContain('peer-focus-visible:outline-2')
            ->not->toContain('role="checkbox"')
            ->not->toContain('tabindex');
    });
});
