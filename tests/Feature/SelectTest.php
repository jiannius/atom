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

    // x-for owns the option rows and the server renders none of them, so a
    // Livewire morph over this subtree pulls rows out from under the loop.
    it('keeps the morph out of the listbox option list', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"listbox\" :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)->toContain('x-show="options.length" class="max-h-[400px] overflow-auto" data-atom-option-list wire:ignore');
    });

    it('keeps the morph out of the filter option list', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"filter\" label=\"Status\" :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)->toContain('x-show="options.length" class="max-h-[400px] overflow-auto" data-atom-option-list wire:ignore');
    });
});

describe('select aria', function () use ($options) {
    it('exposes the listbox variant as an ARIA combobox/listbox', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"listbox\" :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)
            ->toContain('role="listbox"')
            // the id is minted client-side, so both ends of the wiring are bound
            ->toContain('x-bind:id="`${$id(\'atom-select\')}-list`"')
            ->toContain('role="option"')
            ->toContain('x-bind:aria-selected')
            // non-searchable → the trigger button is the combobox host
            ->toContain('role="combobox"')
            ->toContain('aria-haspopup="listbox"')
            ->toContain('data-atom-select-combobox')
            ->toContain('x-bind:aria-controls="`${$id(\'atom-select\')}-list`"')
            // the old menu semantics + DOM-focus marker are gone
            ->not->toContain('role="menuitem"')
            ->not->toContain('data-option-focus');
    });

    it('moves the combobox role onto the search input when searchable', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"listbox\" searchable :options=\"{$options}\" wire:model=\"pick\" />");

        expect($html)
            ->toContain('aria-autocomplete="list"')   // on the search input
            ->toContain('role="combobox"')
            // searchable → the trigger button is not the combobox host
            ->not->toContain('data-atom-select-combobox');
    });

    it('marks a multiple listbox as multiselectable', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"listbox\" multiple :options=\"{$options}\" wire:model=\"picks\" />");

        expect($html)->toContain('aria-multiselectable="true"');
    });

    it('exposes the filter variant as a combobox/listbox too', function () use ($options) {
        $html = renderBlade("<atom:select variant=\"filter\" label=\"Status\" :options=\"{$options}\" wire:model=\"filters.status\" />");

        expect($html)
            ->toContain('role="listbox"')
            ->toContain('role="option"')
            ->toContain('role="combobox"')
            ->not->toContain('role="menuitem"');
    });
});
