<?php

use Illuminate\Support\Facades\Blade;

describe('navlist', function () {
    it('renders a nav wrapper', function () {
        $html = Blade::render('<atom:navlist><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist>');

        expect($html)->toContain('data-atom-navlist')->toContain('<nav');
    });

    it('renders an item as a link with an icon and current-route flag', function () {
        $html = Blade::render('<atom:navlist.item href="/dash" icon="dashboard" :current="true">Dashboard</atom:navlist.item>');

        expect($html)
            ->toContain('data-atom-navlist-item')
            ->toContain('<a')
            ->toContain('href="/dash"')
            ->toContain('Dashboard')
            ->toContain('data-current')
            ->toContain('data-atom-icon');
    });

    it('vertically centers the icon in its wrapper (alignment fix)', function () {
        // Regression: the icon wrapper was a plain <div> whose inline line-box
        // ran taller than the 20px glyph, so items-center pushed the icon high.
        $html = Blade::render('<atom:navlist.item href="/a" icon="dashboard">A</atom:navlist.item>');

        expect($html)->toContain('relative flex shrink-0 items-center');
    });

    it('renders a static heading group', function () {
        $html = Blade::render('<atom:navlist.group heading="Navigation"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('Navigation')->toContain('A');
    });

    it('renders an expandable group with Alpine, not a ui-disclosure web component', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Section"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)
            ->toContain('data-atom-navlist-group')
            ->toContain('x-data="{ open:')
            ->toContain('aria-expanded')
            ->not->toContain('ui-disclosure');
    });
});
