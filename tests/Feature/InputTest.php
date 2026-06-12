<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('input', function () {
    it('renders a text input and derives name from wire:model', function () {
        $html = renderBlade('<atom:input wire:model="title" />');

        expect($html)
            ->toContain('data-atom-input')
            ->toContain('type="text"')
            ->toContain('name="title"');
    });

    it('wraps with a label, caption and required asterisk', function () {
        $html = renderBlade('<atom:input label="Company" caption="Legal name" required />');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('Company')
            ->toContain('data-atom-caption')
            ->toContain('Legal name')
            ->toContain('dark:text-red-300');   // the required-asterisk icon's distinctive class
    });

    it('renders an error message when error is passed', function () {
        $html = renderBlade('<atom:input label="Email" error="Already taken" />');

        expect($html)
            ->toContain('data-atom-error')
            ->toContain('Already taken');
    });

    it('renders prefix and suffix affixes', function () {
        $html = renderBlade('<atom:input label="Site" prefix="https://" suffix=".com" />');

        expect($html)
            ->toContain('https://')
            ->toContain('.com');
    });

    it('exposes the password toggle as a labelled button', function () {
        // Regression: the toggle was a click-only <div> — not focusable, no name.
        $html = renderBlade('<atom:input type="password" label="Password" />');

        expect($html)
            ->toContain('type="button"')
            ->toContain('x-bind:aria-label')
            ->toContain('Show password');
    });

    it('exposes the clearable affix as a labelled button', function () {
        $html = renderBlade('<atom:input clearable />');

        expect($html)
            ->toContain('type="button"')
            ->toContain('aria-label="Clear"');
    });

    it('sets step=any for number inputs', function () {
        $html = renderBlade('<atom:input type="number" />');

        expect($html)
            ->toContain('type="number"')
            ->toContain('step="any"');
    });

    it('renders the tel variant with a country select', function () {
        $html = renderBlade('<atom:input type="tel" />');

        expect($html)
            ->toContain('data-atom-input-tel')
            ->toContain('data-atom-input-tel-country')
            ->toContain('telInput(');
    });

    it('renders the color variant', function () {
        $html = renderBlade('<atom:input type="color" />');

        expect($html)->toContain('data-atom-color-input');
    });

    it('renders the multi-email tag variant', function () {
        $html = renderBlade('<atom:input type="email" multiple />');

        expect($html)
            ->toContain('data-atom-input-email')
            ->toContain('emailInput(');
    });
});
