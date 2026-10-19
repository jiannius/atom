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

it('signals the active state with a solid fill and aria-pressed', function () {
    // The ghost button's hover already tints the fill, so the active
    // (showing-archived) state needs a distinct solid fill plus aria-pressed
    // for assistive tech. The fill/text utilities must be !important — the
    // ghost variant's own text colour otherwise wins on equal specificity
    // (Tailwind orders by utility, not source), leaving a muted icon on the
    // solid active fill. fg on surface flips with the theme, so no dark: pair.
    $html = Blade::render('<atom:table.trashed />');

    expect($html)
        ->toContain('aria-pressed')
        ->toContain('bg-fg! text-surface!')
        ->not->toContain('dark:bg-white!');
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

it('renders the voided variant with the trash icon and label', function () {
    $html = Blade::render('<atom:table.trashed variant="voided" />');

    expect($html)->toContain('aria-label="Show voided"')
        ->and($html)->toContain('Show voided')
        ->and($html)->not->toContain('Show archived');
});

it('forwards a string trashed prop as the variant', function () {
    $html = renderBlade('<atom:table trashed="voided"></atom:table>');

    expect($html)->toContain('data-atom-table-trashed')
        ->and($html)->toContain('aria-label="Show voided"');
});
