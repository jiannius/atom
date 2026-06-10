<?php

it('renders skeleton rows when skeleton is set and no paginator is loaded', function () {
    $html = renderBlade('<atom:table skeleton></atom:table>');

    expect($html)->toContain('data-atom-table-skeleton');
});

it('renders the given number of skeleton rows', function () {
    $html = renderBlade('<atom:table :skeleton="3"></atom:table>');

    expect(substr_count($html, 'data-atom-table-skeleton-row'))->toBe(3);
});

it('does NOT render skeleton for a static table without the flag', function () {
    $html = renderBlade(<<<'BLADE'
        <atom:table :empty="false">
            <x-slot:rows>
                <atom:table.row><atom:table.cell>Jane</atom:table.cell></atom:table.row>
            </x-slot:rows>
        </atom:table>
    BLADE);

    expect($html)->not->toContain('data-atom-table-skeleton')
        ->and($html)->toContain('Jane');
});
