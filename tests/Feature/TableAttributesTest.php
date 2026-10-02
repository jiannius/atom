<?php

/**
 * Render a one-row static table with the given attributes on <atom:table>.
 */
function tableAttributesRender(string $attributes = ''): string
{
    return renderBlade(<<<BLADE
        <atom:table :empty="false" {$attributes}>
            <x-slot:rows>
                <atom:table.row><atom:table.cell>Jane</atom:table.cell></atom:table.row>
            </x-slot:rows>
        </atom:table>
    BLADE);
}

// The root div printed a literal class list and never the bag, so a margin, an
// id or an x-show passed to <atom:table> was dropped without a trace.
it('prints the caller attributes on the table root', function () {
    $roots = domQuery(tableAttributesRender('id="invoices" class="mt-8" x-show="ready" data-test="t"'), '//*[@data-atom-table]');

    expect($roots)->toHaveCount(1);

    $root = $roots[0];

    expect($root->getAttribute('id'))->toBe('invoices')
        ->and($root->getAttribute('x-show'))->toBe('ready')
        ->and($root->getAttribute('data-test'))->toBe('t')
        ->and(domClasses($root))->toContain('mt-8', 'group/table', 'space-y-4');
});

it('keeps the root defaults and the filter-changed listener when the caller passes nothing', function () {
    $root = domQuery(tableAttributesRender(), '//*[@data-atom-table]')[0];

    expect(domClasses($root))->toBe(['group/table', 'space-y-4'])
        ->and($root->getAttribute('x-data'))->toBe('{}')
        ->and($root->hasAttribute('x-on:table-filter:changed.window'))->toBeTrue();
});

it('lets a caller x-data replace the empty default instead of printing two', function () {
    $html = tableAttributesRender('x-data="{ open: true }"');

    expect(substr_count($html, 'x-data="{ open: true }"'))->toBe(1);

    $root = domQuery($html, '//*[@data-atom-table]')[0];

    expect($root->getAttribute('x-data'))->toBe('{ open: true }')
        ->and($root->hasAttribute('x-on:table-filter:changed.window'))->toBeTrue();
});

// `table-fixed` did nothing under `min-w-full` (no width, so the browser falls back to
// auto layout) but read as if columns were fixed width; callers' widths are honoured as is.
it('does not carry the inert table-fixed class', function () {
    $table = domQuery(tableAttributesRender(), '//table')[0];

    expect(domClasses($table))->not->toContain('table-fixed')->toContain('min-w-full');
});
