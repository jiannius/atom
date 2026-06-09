<?php

use Illuminate\Support\Facades\Blade;

it('renders the filter bar with the chips listener hook', function () {
    $html = Blade::render('<atom:table.filters><div>control</div></atom:table.filters>');

    expect($html)->toContain('data-atom-table-filters')
        ->and($html)->toContain('table-filter:set')
        ->and($html)->toContain('Clear all');
});

it('renders the overflow popover by default', function () {
    $html = renderBlade('<atom:table.filters><x-slot:more><div>x</div></x-slot:more>main</atom:table.filters>');

    expect($html)->toContain('More filters');
});

it('renders an expandable card when overflow=card', function () {
    $html = renderBlade('<atom:table.filters overflow="card"><x-slot:more><div>x</div></x-slot:more>main</atom:table.filters>');

    expect($html)->toContain('expanded = !expanded');
});

it('does not register a select that has no wire:model', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<atom:select variant="filter" label="Status" :options="[]" />');
    expect($html)->not->toContain('table-filter:set');
});
