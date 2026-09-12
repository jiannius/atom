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

describe('input label association', function () {
    // Reported against smgdms (#65): on the New Client form, 17 of 18 visible inputs had no
    // label[for], no wrapping <label> and no aria-label, so assistive tech announced them as
    // an unnamed "edit text". The visible "Name" / "Email" text was presentational only.
    // Playwright's getByLabel could not resolve a single field, which is a fair proxy for
    // what a screen reader gets.

    it('associates the label with the input it labels', function () {
        $html = renderBlade('<atom:input label="Company" wire:model="company" />');

        preg_match('/<label[^>]*\bfor="([^"]+)"/', $html, $labelFor);
        preg_match('/<input[^>]*\bid="([^"]+)"/', $html, $inputId);

        expect($labelFor[1] ?? null)->not->toBeNull('the label has no for attribute')
            ->and($inputId[1] ?? null)->not->toBeNull('the input has no id')
            ->and($labelFor[1])->toBe($inputId[1]);
    });

    it('keeps an id the caller supplied rather than overwriting it', function () {
        $html = renderBlade('<atom:input label="Company" id="my-own-id" />');

        expect($html)->toContain('id="my-own-id"')
            ->and($html)->toContain('for="my-own-id"');
    });

    it('gives two inputs on the same page different ids', function () {
        $html = renderBlade('<div><atom:input label="One" wire:model="a" /><atom:input label="Two" wire:model="b" /></div>');

        preg_match_all('/<input[^>]*\bid="([^"]+)"/', $html, $ids);

        expect($ids[1])->toHaveCount(2)
            ->and($ids[1][0])->not->toBe($ids[1][1]);
    });

    it('does not emit a for attribute when there is no label', function () {
        $html = renderBlade('<atom:input wire:model="company" />');

        expect($html)->not->toContain('<label');
    });
});
