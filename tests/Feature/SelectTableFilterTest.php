<?php

beforeEach(function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
});

/** Render an <atom:select> with the given attribute string. */
function renderSelect(string $attrs): string
{
    return renderBlade('<atom:select '.$attrs.' :options="[[\'value\' => \'red\', \'label\' => \'Red\']]" />');
}

it('leaves a select alone when the flag is absent', function (string $variant) {
    $html = renderSelect('variant="'.$variant.'" label="Colour" wire:model="filters.colour"');

    expect($html)->not->toContain('table-filter:set')
        ->and($html)->not->toContain('table-filter:do-clear');
})->with(['listbox', 'native']);

it('registers as a chip when flagged, keyed by the model', function (string $variant) {
    $html = renderSelect('variant="'.$variant.'" label="Colour" wire:model="filters.colour" table-filter');

    expect($html)->toContain('table-filter:set')
        ->and($html)->toContain("key: 'filters.colour'")
        ->and($html)->toContain('table-filter:do-clear.window')
        // and the change fires the table's selection reset, as a bar control does
        ->and($html)->toContain('table-filter:changed');
})->with(['listbox', 'native']);

it('names the chip after the field label', function (string $variant) {
    $html = renderSelect('variant="'.$variant.'" label="Paint colour" wire:model="filters.colour" table-filter');

    expect($html)->toContain("label: 'Paint colour'");
})->with(['listbox', 'native']);

it('falls back to the key when the field has no label', function (string $variant) {
    // a label-less chip would otherwise render as "null: Red"
    $html = renderSelect('variant="'.$variant.'" wire:model="filters.paint_colour" table-filter');

    expect($html)->toContain("label: 'Paint Colour'");
})->with(['listbox', 'native']);

it('takes data-filter-key when there is no wire:model', function (string $variant) {
    $html = renderSelect('variant="'.$variant.'" label="Colour" x-model="filters.colour" data-filter-key="filters.colour" table-filter');

    expect($html)->toContain('table-filter:set')
        ->and($html)->toContain("key: 'filters.colour'");
})->with(['listbox', 'native']);

it('stays silent when flagged with nothing to key the chip by', function (string $variant) {
    // no model, no data-filter-key: a chip with no key can never be cleared, so
    // the flag is a no-op rather than half a registration
    $html = renderSelect('variant="'.$variant.'" label="Colour" table-filter');

    expect($html)->not->toContain('table-filter:set');
})->with(['listbox', 'native']);

it('keeps the flag out of the rendered markup', function (string $variant) {
    $html = renderSelect('variant="'.$variant.'" label="Colour" wire:model="filters.colour" table-filter');

    expect($html)->not->toContain('table-filter="')
        ->and($html)->not->toContain('table-filter-label');
})->with(['listbox', 'native']);

it('reads the native variant display out of the option elements', function () {
    $html = renderSelect('variant="native" label="Colour" wire:model="filters.colour" table-filter');

    expect($html)->toContain('tableFilterDisplay')
        // the value is what the model holds; the chip has to show the option's text
        ->and($html)->toContain('querySelectorAll(\'option\')');
});
