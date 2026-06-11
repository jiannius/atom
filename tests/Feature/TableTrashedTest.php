<?php

use Illuminate\Support\Facades\Blade;

it('renders an icon-only toggle with a tooltip bound to _table.show_trashed', function () {
    $html = Blade::render('<atom:table.trashed />');

    expect($html)->toContain('data-atom-table-trashed')
        ->and($html)->toContain('_table.show_trashed')
        ->and($html)->toContain('data-atom-tooltip')
        ->and($html)->toContain('aria-label="Show archived"')
        ->and($html)->toContain('Show archived');
});

it('renders the toggle inside the table header via the trashed prop', function () {
    $html = renderBlade('<atom:table trashed></atom:table>');

    expect($html)->toContain('data-atom-table-trashed');
});

it('appends the toggle after the header slot content', function () {
    $html = renderBlade(<<<'BLADE'
        <atom:table trashed>
            <x-slot:header><div>FILTERS</div></x-slot:header>
        </atom:table>
    BLADE);

    expect($html)->toContain('data-atom-table-trashed')
        ->and(strpos($html, 'FILTERS'))->toBeLessThan(strpos($html, 'data-atom-table-trashed'));
});

it('does not render the toggle without the trashed prop', function () {
    $html = renderBlade('<atom:table></atom:table>');

    expect($html)->not->toContain('data-atom-table-trashed');
});
