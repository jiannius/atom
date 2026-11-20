<?php

use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Services\Attributes;

/**
 * #235: `<atom:table rows-toggle-when-selected>`. The click behaviour is in tests/e2e/table-rows-toggle.spec.js; this holds what the markup
 * says, and that a table without the prop prints none of it (the goldens in tests/Fixtures/golden hold the rest byte for byte).
 */

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/** A table with a bulk bar and one row with a checkbox cell and an href, with or without the prop. */
function rowsToggleTable(string $attributes = ''): string
{
    return renderBlade(
        '<atom:table '.$attributes.'><x-slot:checked><button>Send</button></x-slot:checked><x-slot:rows><atom:table.row href="/x"><atom:table.cell checkbox="1" /></atom:table.row></x-slot:rows></atom:table>',
    );
}

/** One attribute of the table's root element, decoded. */
function rowsToggleRootAttribute(string $html, string $name): ?string
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $root = (new DOMXPath($dom))->query('//*[@data-atom-table]')->item(0);

    return $root->hasAttribute($name) ? $root->getAttribute($name) : null;
}

it('prints no listener, marker or hint without the prop', function () {
    $html = rowsToggleTable();

    expect(rowsToggleRootAttribute($html, 'x-on:click.capture'))->toBeNull()
        ->and(rowsToggleRootAttribute($html, 'x-on:keydown.enter.capture'))->toBeNull()
        ->and($html)->not->toContain('data-atom-rows-toggle')
        ->and($html)->not->toContain('Untick all to open a record');
});

it('prints the same table for a false prop as for no prop', function () {
    expect(rowsToggleTable(':rows-toggle-when-selected="false"'))->toBe(rowsToggleTable());
});

it('puts the rows-toggle directive on the table root with the prop, given the selector of the controls a click is left to', function () {
    $html = rowsToggleTable('rows-toggle-when-selected');
    $directive = rowsToggleRootAttribute($html, 'x-atom-rows-toggle');

    expect($html)->toContain('data-atom-rows-toggle')
        ->and(json_decode($directive))->toBe(Attributes::ROW_INTERACTIVE)
        ->and($directive)->toContain('[role=checkbox]')
        ->and(rowsToggleRootAttribute($html, 'x-on:click.capture'))->toBeNull()
        ->and(rowsToggleRootAttribute($html, 'x-on:keydown.enter.capture'))->toBeNull();
});

it('guards a click on a control inside the row with the same controls the href row uses', function () {
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/helpers/rows-toggle.js');
    $guard = Attributes::rowGuard('row');

    // the selector comes from the same constant; the attribute prefixes rowGuard() walks for are the ones the directive walks for
    expect($guard)->toContain(Attributes::ROW_INTERACTIVE)
        ->and($guard)->toContain("['wire:click', 'x-on:click', '@click']")
        ->and($js)->toContain("['wire:click', 'x-on:click', '@click']");

    $html = renderBlade('<atom:table.row href="/x" />');

    expect(html_entity_decode($html, ENT_QUOTES))->toContain(Attributes::rowGuard('e.currentTarget'));
});

it('says in the checked bar how to open a record again, through t()', function () {
    $html = rowsToggleTable('rows-toggle-when-selected');

    expect($html)->toContain('data-atom-table-rows-toggle-hint')
        ->and($html)->toContain('Untick all to open a record');

    app()->setLocale('xx');
    app('translator')->addLines(['messages.untick-to-open-record' => 'Translated hint'], 'xx', 'atom');

    expect(rowsToggleTable('rows-toggle-when-selected'))->toContain('Translated hint');
});

it('swallows a repeated Enter, a double-click and a click after a text selection on a toggle row', function () {
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/helpers/rows-toggle.js');

    expect($js)->toContain('$wire._table?.checkboxes?.length || $wire._table?.select_all')
        ->and($js)->toContain('e.ctrlKey || e.metaKey')
        ->and($js)->toContain('[data-atom-table-checkbox]')
        ->and($js)->toContain('[data-atom-table-row]')
        ->and($js)->toContain('box.click()')
        ->and($js)->toContain('e.detail > 1')
        ->and($js)->toContain('row.contains(selection.anchorNode)')
        ->and($js)->toContain('e.repeat');
});

it('prints a translated hint escaped', function () {
    app()->setLocale('xx');
    app('translator')->addLines(['messages.untick-to-open-record' => '<b>Untick</b> & go'], 'xx', 'atom');

    $html = rowsToggleTable('rows-toggle-when-selected');

    expect($html)->toContain('&lt;b&gt;Untick&lt;/b&gt; &amp; go')
        ->and($html)->not->toContain('<b>Untick</b>');
});
