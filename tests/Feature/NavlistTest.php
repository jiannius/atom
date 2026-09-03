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
            ->toContain('x-data=')
            ->toContain('open:')
            ->toContain('aria-expanded')
            ->not->toContain('ui-disclosure');
    });
});

describe('navlist.group persist-key', function () {
    it('stays inert without the prop — no key, no storage access', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Section"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)
            ->toContain('key: null')
            ->toContain('open: true');
    });

    it('namespaces the storage key so a caller cannot collide with the app', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Purchase" persist-key="nav.purchase"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // a caller passing "sidebar" must not read or write a bare "sidebar" entry
        expect($html)->toContain('atom:navlist-group:nav.purchase');
    });

    it('guards both reads and writes, so a throwing localStorage cannot kill the component', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // Safari private mode throws on access; an uncaught throw in init() would
        // take the whole Alpine component down and stop the group toggling at all
        expect(substr_count($html, 'try {'))->toBe(2)
            ->and($html)->toContain('window.localStorage.getItem')
            ->and($html)->toContain('window.localStorage.setItem');
    });

    it('persists via $watch, so any other flip of open is stored too', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // not hooked to the click handler — a keyboard shortcut or a programmatic
        // collapse-all persists for free, and x-on:click stays as it was
        expect($html)
            ->toContain("\$watch('open'")
            ->toContain('x-on:click="open = !open"');
    });

    it('still renders expanded as the initial value; storage overrides at runtime', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" :expanded="false" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('open: false');
    });

    it('ignores the prop on a non-expandable group', function () {
        $html = Blade::render('<atom:navlist.group heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->not->toContain('atom:navlist-group:k');
    });
});
