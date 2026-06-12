<?php

use Illuminate\Support\Facades\Blade;

describe('label', function () {
    it('renders a label element with an optional icon', function () {
        $html = Blade::render('<atom:label icon="close">Name</atom:label>');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('<label')
            ->toContain('Name')
            ->toContain('data-atom-icon');
    });

    it('lays out an actions slot alongside the label', function () {
        $html = Blade::render(<<<'BLADE'
            <atom:label>
                Name
                <x-slot:actions><span>edit</span></x-slot:actions>
            </atom:label>
        BLADE);

        expect($html)
            ->toContain('Name')
            ->toContain('edit');
    });
});

describe('error', function () {
    it('renders a bullet list from the errors attribute', function () {
        $html = Blade::render('<atom:error :errors="[\'Required\', \'Too short\']" />');

        expect($html)
            ->toContain('data-atom-error')
            ->toContain('<li>Required</li>')
            ->toContain('<li>Too short</li>');
    });

    it('renders slot content when no errors array is given', function () {
        $html = Blade::render('<atom:error>Bad value</atom:error>');

        expect($html)
            ->toContain('data-atom-error')
            ->toContain('Bad value');
    });

    it('renders nothing when empty', function () {
        $html = Blade::render('<atom:error />');

        expect(trim($html))->not->toContain('data-atom-error');
    });
});

describe('caption', function () {
    it('renders caption content', function () {
        $html = Blade::render('<atom:caption>Helper text</atom:caption>');

        expect($html)
            ->toContain('data-atom-caption')
            ->toContain('Helper text');
    });
});
