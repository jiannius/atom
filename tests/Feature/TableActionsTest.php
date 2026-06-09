<?php

use Illuminate\Support\Facades\Blade;

it('renders a trailing actions cell with a dropdown menu', function () {
    $html = Blade::render('<atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>');

    expect($html)->toContain('data-atom-table-actions')
        ->and($html)->toContain('click.stop')
        ->and($html)->toContain('Edit');
});
