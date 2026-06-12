<?php

use Illuminate\Support\Facades\Blade;

describe('breadcrumbs', function () {
    it('renders the breadcrumbs Alpine factory wired to navigation events', function () {
        $html = Blade::render('<atom:breadcrumbs />');

        expect($html)
            ->toContain('data-atom-breadcrumbs')
            ->toContain('breadcrumbs()')
            ->toContain('x-on:livewire:navigated.window="build()"')
            ->toContain('x-on:navigate-back.window="back()"');
    });

    it('renders the single-crumb heading branch by default', function () {
        $html = Blade::render('<atom:breadcrumbs />');

        // heading prop defaults true → the lone-crumb branch renders a heading
        expect($html)->toContain('breadcrumbs.length === 1');
    });

    it('can suppress the heading branch', function () {
        $html = Blade::render('<atom:breadcrumbs :heading="false" />');

        expect($html)
            ->toContain('data-atom-breadcrumbs')
            ->not->toContain('breadcrumbs.length === 1');
    });
});
