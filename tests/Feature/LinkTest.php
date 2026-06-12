<?php

use Illuminate\Support\Facades\Blade;

describe('link', function () {
    it('renders an anchor with the dotted-underline style', function () {
        $html = Blade::render('<atom:link href="/docs">Read the docs</atom:link>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/docs"')
            ->toContain('Read the docs')
            ->toContain('underline')
            ->toContain('decoration-dotted');
    });

    it('renders a leading icon', function () {
        $html = Blade::render('<atom:link href="/x" icon="link">Linked</atom:link>');

        expect($html)
            ->toContain('data-atom-icon')
            ->toContain('inline-flex items-center gap-2');
    });

    it('falls back to the href as text when no slot is given', function () {
        $html = Blade::render('<atom:link href="https://example.com" />');

        expect($html)->toContain('https://example.com');
    });

    it('opens in a new tab when newtab is set', function () {
        $html = Blade::render('<atom:link href="/x" newtab>Go</atom:link>');

        expect($html)->toContain('target="_blank"');
    });
});
