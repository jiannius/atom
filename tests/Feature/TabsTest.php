<?php

use Illuminate\Support\Facades\Blade;

describe('tabs', function () {
    it('renders tabs from the tabs array and dispatches input on click', function () {
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
            ->toContain("\$dispatch('input', 'overview')");
    });

    it('renders the button variant chrome', function () {
        $html = Blade::render('<atom:tabs variant="button" :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)->toContain('bg-zinc-100');
    });

    it('marks the current tab active', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'value\' => \'a\', \'current\' => true]]" />');

        expect($html)->toContain('data-active');
    });
});
