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

// Cells and headers wrapped never: whitespace-nowrap was a plain utility, so a
// caller's whitespace-normal lost to it on the compiled-order tie. The default
// now sits at zero specificity, like modal/menu's min/max widths.
describe('wrapping', function () {
    it('defaults a cell to nowrap at zero specificity', function () {
        $cell = domQuery(renderBlade('<atom:table.cell>Jane</atom:table.cell>'), '//td')[0];

        expect(domClasses($cell))
            ->toContain('[:where(&)]:whitespace-nowrap')
            ->not->toContain('whitespace-nowrap');
    });

    it('lets a caller whitespace utility reach the td beside the default', function () {
        $cell = domQuery(renderBlade('<atom:table.cell class="whitespace-normal md:whitespace-pre-line">Jane</atom:table.cell>'), '//td')[0];

        expect(domClasses($cell))->toContain('whitespace-normal', 'md:whitespace-pre-line', '[:where(&)]:whitespace-nowrap');
    });

    it('defaults a header to nowrap on the th, not the inner wrapper', function () {
        $html = renderBlade('<atom:table.column>Contact</atom:table.column>');
        $th = domQuery($html, '//th')[0];
        $wrapper = domQuery($html, '//th/div')[0];

        expect(domClasses($th))->toContain('[:where(&)]:whitespace-nowrap')->not->toContain('whitespace-nowrap')
            // the wrapper keeps its spacing and carries no whitespace utility of its own
            ->and(domClasses($wrapper))->toContain('py-1.5', 'px-3')
            ->and(implode(' ', domClasses($wrapper)))->not->toContain('whitespace');
    });

    it('lets a caller whitespace-normal wrap a header label', function () {
        $html = renderBlade('<atom:table.column class="whitespace-normal">Contact person</atom:table.column>');
        $th = domQuery($html, '//th')[0];

        expect(domClasses($th))->toContain('whitespace-normal', '[:where(&)]:whitespace-nowrap');
        // one place only: on the th, where it inherits into the label
        expect(substr_count($html, 'whitespace-normal'))->toBe(1);
    });

    it('sizes a checkbox header on the th rather than the inner wrapper', function () {
        $html = renderBlade('<atom:table.column checkbox />');

        expect(domClasses(domQuery($html, '//th')[0]))->toContain('w-10')
            ->and(domClasses(domQuery($html, '//th/div')[0]))->not->toContain('w-10');
    });

    it('leaves a plain header without a width', function () {
        expect(domClasses(domQuery(renderBlade('<atom:table.column>Contact</atom:table.column>'), '//th')[0]))->not->toContain('w-10');
    });
});
