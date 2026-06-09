<?php

use Illuminate\Support\Facades\Blade;

it('renders a toggle bound to _table.show_trashed', function () {
    $html = Blade::render('<atom:table.trashed />');

    expect($html)->toContain('data-atom-table-trashed')
        ->and($html)->toContain('_table.show_trashed');
});
