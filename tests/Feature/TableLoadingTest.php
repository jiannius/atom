<?php

use Illuminate\Support\Facades\Blade;

it('renders a pagination/sort loading overlay targeting the right methods', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);

    $html = Blade::render(<<<'BLADE'
        <atom:table :empty="false">
            <x-slot:columns><atom:table.column>Name</atom:table.column></x-slot:columns>
            <x-slot:rows><atom:table.row><atom:table.cell>A</atom:table.cell></atom:table.row></x-slot:rows>
        </atom:table>
    BLADE);

    expect($html)->toContain('wire:loading')
        ->and($html)->toContain('gotoPage,nextPage,previousPage,_table.sort.column,_table.sort.direction');
});
