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

    // A <label for> naming a wrapper <div> is not an association at all — the field stays
    // unnamed — but it still looks fixed to anything that only checks that `for` resolves
    // to some element. tel/color/multi-email hand the attribute bag to their Alpine wrapper,
    // so the id has to be routed past it onto the control itself.
    it('points the label at a real form control, not at a wrapper', function (string $template, string $tag) {
        $html = renderBlade($template);

        preg_match('/<label[^>]*\bfor="([^"]+)"/', $html, $for);
        expect($for[1] ?? null)->not->toBeNull('the label has no for attribute');

        preg_match('/<([a-z]+)[^>]*\bid="'.preg_quote($for[1], '/').'"/', $html, $target);
        expect($target[1] ?? null)->toBe($tag, 'for points at <'.($target[1] ?? 'nothing').'>, which names nothing');
    })->with([
        'text' => ['<atom:input label="Company" wire:model="company" />', 'input'],
        'password' => ['<atom:input type="password" label="Company" wire:model="company" />', 'input'],
        'number' => ['<atom:input type="number" label="Company" wire:model="company" />', 'input'],
        'tel' => ['<atom:input type="tel" label="Company" wire:model="company" />', 'input'],
        'color' => ['<atom:input type="color" label="Company" wire:model="company" />', 'input'],
        'email' => ['<atom:input type="email" label="Company" wire:model="company" />', 'input'],
        'email multiple' => ['<atom:input type="email" multiple label="Company" wire:model="company" />', 'input'],
        'textarea' => ['<atom:textarea label="Company" wire:model="company" />', 'textarea'],
    ]);

    // Livewire's morph falls back to `id` as its key when nothing else keys the element
    // (`key: el => ... : el.id`), so an id that changes per render makes the morph replace
    // the control instead of patching it: focus and the caret are lost mid-typing on any
    // wire:model.live field, and cached node references (input.general's clearable button)
    // go stale. The id must be derived from the field, never minted at random.
    // tests/e2e/input-morph.spec.js is the browser-level guard.
    it('mints the same id on every render, because the id is a morph key', function () {
        $first = renderBlade('<atom:input label="Company" wire:model="company" />');
        $second = renderBlade('<atom:input label="Company" wire:model="company" />');

        preg_match('/<input[^>]*\bid="([^"]+)"/', $first, $a);
        preg_match('/<input[^>]*\bid="([^"]+)"/', $second, $b);

        expect($a[1] ?? null)->not->toBeNull('the input has no id')
            ->and($b[1] ?? null)->toBe($a[1], 'the id churned between two renders of the same field');
    });

    it('keeps an id the caller supplied rather than overwriting it', function () {
        $html = renderBlade('<atom:input label="Company" id="my-own-id" />');

        expect($html)->toContain('id="my-own-id"')
            ->and($html)->toContain('for="my-own-id"');
    });

    it('keeps a caller id of "0", which is legal HTML but falsy in PHP', function () {
        $html = renderBlade('<atom:input label="Qty" id="0" />');

        expect($html)->toContain('id="0"')
            ->and($html)->toContain('for="0"');
    });

    it('gives two inputs on the same page different ids', function () {
        $html = renderBlade('<div><atom:input label="One" wire:model="a" /><atom:input label="Two" wire:model="b" /></div>');

        preg_match_all('/<input[^>]*\bid="([^"]+)"/', $html, $ids);

        expect($ids[1])->toHaveCount(2)
            ->and($ids[1][0])->not->toBe($ids[1][1]);
    });

    // Nothing points at it, and an id is what drags an element into the morph swap above,
    // so a field with no label is left exactly as it was.
    it('mints no id when there is no label to point at', function (string $template) {
        $html = renderBlade($template);

        expect($html)->not->toContain('id="atom-input-')
            ->and($html)->not->toContain('for=');
    })->with([
        'no label' => ['<atom:input wire:model="company" />'],
        'caption only' => ['<atom:input caption="Legal name" wire:model="company" />'],
    ]);

    // The uploader's real <input type="file"> is class="hidden", so it is out of the
    // accessibility tree and a <label for> pointing at it computes no accessible name.
    // The field is named through the uploader's visible trigger button instead — see
    // FieldNameTest — so `for` must stay off the label rather than dangle at a hidden
    // control that can never carry the name.
    it('emits no for on a file input, whose real control is hidden', function () {
        $html = renderBlade('<atom:input type="file" label="Attachment" wire:model="doc" />');

        expect($html)->toContain('<label')
            ->and($html)->not->toContain('for="atom-input-');
    });
});
