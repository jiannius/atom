<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('textarea', function () {
    it('renders a textarea with the given rows', function () {
        $html = Blade::render('<atom:textarea rows="5" placeholder="Notes" />');

        expect($html)
            ->toContain('data-atom-textarea')
            ->toContain('rows="5"')
            ->toContain('placeholder="Notes"');
    });

    it('wires autosize when autoresize is set', function () {
        $html = Blade::render('<atom:textarea autoresize />');

        expect($html)
            ->toContain('x-autosize')
            ->toContain('$autosize()');
    });

    it('wraps with label, caption and error via the field', function () {
        $html = renderBlade('<atom:textarea label="Bio" caption="Max 200" error="Too long" />');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('Bio')
            ->toContain('data-atom-caption')
            ->toContain('data-atom-error')
            ->toContain('Too long');
    });

    it('drops the box chrome for the transparent variant', function () {
        $html = Blade::render('<atom:textarea variant="transparent" />');

        expect($html)
            ->toContain('bg-transparent')
            ->toContain('border-0');
    });
});

describe('textarea label association', function () {
    it('associates the label with the textarea it labels', function () {
        $html = renderBlade('<atom:textarea label="Notes" wire:model="notes" />');

        preg_match('/<label[^>]*\bfor="([^"]+)"/', $html, $labelFor);
        preg_match('/<textarea[^>]*\bid="([^"]+)"/', $html, $id);

        expect($labelFor[1] ?? null)->not->toBeNull('the label has no for attribute')
            ->and($id[1] ?? null)->not->toBeNull('the textarea has no id')
            ->and($labelFor[1])->toBe($id[1]);
    });

    // Same reason as <atom:input>: the id doubles as Livewire's morph key, so one that
    // churns per render makes the morph replace the textarea instead of patching it.
    it('mints the same id on every render, because the id is a morph key', function () {
        preg_match('/<textarea[^>]*\bid="([^"]+)"/', renderBlade('<atom:textarea label="Notes" wire:model="notes" />'), $a);
        preg_match('/<textarea[^>]*\bid="([^"]+)"/', renderBlade('<atom:textarea label="Notes" wire:model="notes" />'), $b);

        expect($a[1] ?? null)->not->toBeNull('the textarea has no id')
            ->and($b[1] ?? null)->toBe($a[1], 'the id churned between two renders of the same field');
    });

    it('mints no id when there is no label to point at', function () {
        $html = renderBlade('<atom:textarea wire:model="notes" />');

        expect($html)->not->toContain('id="atom-textarea-')
            ->and($html)->not->toContain('for=');
    });
});
