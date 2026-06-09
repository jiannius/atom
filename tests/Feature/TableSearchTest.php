<?php

use Illuminate\Support\Facades\Blade;

it('renders a search input with the enter-to-search handler', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<atom:table.search wire:model="filters.search" />');

    expect($html)->toContain('data-atom-table-search')
        ->and($html)->toContain('keyup.enter')
        ->and($html)->toContain('filters.search');
});
