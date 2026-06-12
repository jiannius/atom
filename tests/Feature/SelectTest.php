<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

$options = "[['value' => 1, 'label' => 'One'], ['value' => 2, 'label' => 'Two']]";

describe('select', function () use ($options) {
    it('renders the native variant by default with options and placeholder', function () use ($options) {
        $html = renderBlade("<atom:select :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)
            ->toContain('data-atom-select-native')
            ->toContain('<select')
            ->toContain('One')
            ->toContain('Two')
            ->toContain('Please select...');
    });

    it('renders the listbox variant driven by the select() factory', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"listbox\" :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)
            ->toContain('data-atom-select-listbox')
            ->toContain('select(');
    });

    it('renders the filter variant with its label', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"filter\" label=\"Status\" :options=\"{$options}\" wire:model=\"filters.status\" />");

        expect($html)
            ->toContain('select(')
            ->toContain('Status');
    });

    it('wraps with label, caption and error via the field', function () use ($options) {
        $html = renderBlade("<atom:select label=\"Country\" caption=\"Pick one\" error=\"Required\" :options=\"{$options}\" />");

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('Country')
            ->toContain('data-atom-caption')
            ->toContain('data-atom-error')
            ->toContain('Required');
    });

    it('renders the multiple native variant', function () use ($options) {
        $html = renderBlade("<atom:select multiple :options=\"{$options}\" wire:model=\"picks\" />");

        expect($html)
            ->toContain('data-atom-select-native')
            ->toContain('multiple: true');
    });
});
