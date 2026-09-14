<?php

use Illuminate\Support\Facades\Blade;

/**
 * The browser tests (tests/e2e/table-loading.spec.js) own the behaviour. These
 * pin the served contract they cannot see cheaply — and, for the CSS rule, the
 * thing no rig without Tailwind can see at all.
 */
describe('table loading overlay', function () {
    beforeEach(function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $this->html = Blade::render(<<<'BLADE'
            <atom:table :empty="false">
                <x-slot:columns><atom:table.column>Name</atom:table.column></x-slot:columns>
                <x-slot:rows><atom:table.row><atom:table.cell>A</atom:table.cell></atom:table.row></x-slot:rows>
            </atom:table>
        BLADE);
    });

    it('targets every atom-owned control that swaps the result set out', function () {
        // Listed one at a time on purpose: asserting the whole string as a substring
        // is a prefix match, so it kept passing when the last two were missing.
        expect($this->html)->toContain('wire:loading');

        foreach ([
            'gotoPage', 'nextPage', 'previousPage',
            '_table.sort.column', '_table.sort.direction',
            '_table.max_rows', '_table.show_trashed',
        ] as $target) {
            expect($this->html)->toContain($target);
        }
    });

    it('does not hold the overlay down with the hidden attribute', function () {
        // Tailwind's Preflight hides [hidden] with !important, which outranks the
        // inline display Livewire sets — so an overlay marked `hidden` can never
        // appear in a consuming app. No rig without Preflight can catch that.
        expect($this->html)->not->toMatch('/<div[^>]*wire:loading[^>]*\shidden[\s>]/');
    });

    it('is held down by an unlayered rule in atom.css, and not with !important', function () {
        // Livewire ships the same rule but only injects its stylesheet on a request
        // that rendered a component, which leaves a plain-Blade table uncovered.
        $css = file_get_contents(__DIR__.'/../../resources/css/atom.css');

        expect($css)->toMatch('/\[wire\\\\:loading\\\\\.flex\][^{]*\{[^}]*display:\s*none\s*;/')
            ->and($css)->not->toMatch('/\[wire\\\\:loading\][^{]*\{[^}]*display:\s*none\s*!important/');
    });
});
