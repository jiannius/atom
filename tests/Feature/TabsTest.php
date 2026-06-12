<?php

use Illuminate\Support\Facades\Blade;

describe('tabs', function () {
    it('binds wire:model through x-modelable, not bubbled input events', function () {
        // Regression (LW4): wire:model now adds `.self` to its underlying
        // x-model, so a child <button> dispatching `input` no longer reaches
        // the <div wire:model>. The tabs expose a reactive `value` via
        // x-modelable instead — the same mechanism <atom:select> uses.
        $html = Blade::render(<<<'BLADE'
            <atom:tabs wire:model="tab" :tabs="[
                ['label' => 'Overview', 'value' => 'overview'],
                ['label' => 'Activity', 'value' => 'activity'],
            ]" />
        BLADE);

        expect($html)
            ->toContain('data-atom-tabs')
            ->toContain('Overview')
            ->toContain('Activity')
            ->toContain('x-modelable="value"')
            ->toContain('x-data="{ value: $wire.tab }"')
            ->toContain('x-on:tabs-input="value = $event.detail"')
            ->toContain("\$dispatch('tabs-input', 'overview')")
            ->toContain('x-bind:data-active="value === \'overview\'"')
            // the old, .self-broken wiring must be gone
            ->not->toContain("\$dispatch('input',")
            ->not->toContain('$wire.tab ===');
    });

    it('renders the button variant chrome', function () {
        $html = Blade::render('<atom:tabs variant="button" :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)->toContain('bg-zinc-100');
    });

    it('marks the current tab active', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'value\' => \'a\', \'current\' => true]]" />');

        expect($html)->toContain('data-active');
    });

    it('stays static — no alpine state — without wire:model', function () {
        // The second supported use case: slot items as plain buttons/links with
        // no Livewire binding. No x-data/x-modelable should be emitted.
        $html = Blade::render(<<<'BLADE'
            <atom:tabs>
                <atom:tabs.item label="Profile" current/>
                <atom:tabs.item label="Billing" href="/billing"/>
            </atom:tabs>
        BLADE);

        expect($html)
            ->toContain('data-atom-tabs')
            ->toContain('data-active')              // current
            ->toContain('href="/billing"')          // link tab renders an <a>
            ->not->toContain('x-modelable')
            ->not->toContain('x-data')
            ->not->toContain('tabs-input');
    });
});
