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

it('puts a capture-phase click and Enter listener on the table root with the prop', function () {
    $html = rowsToggleTable('rows-toggle-when-selected');
    $click = rowsToggleRootAttribute($html, 'x-on:click.capture');
    $enter = rowsToggleRootAttribute($html, 'x-on:keydown.enter.capture');

    expect($html)->toContain('data-atom-rows-toggle')
        ->and($click)->toContain('$wire._table?.checkboxes?.length || $wire._table?.select_all')
        ->and($click)->toContain('e.ctrlKey || e.metaKey')
        ->and($click)->toContain('[data-atom-table-checkbox]')
        ->and($click)->toContain('e.stopPropagation()')
        ->and($click)->toContain('box.click()')
        ->and($enter)->toContain('$wire._table?.checkboxes?.length || $wire._table?.select_all')
        ->and($enter)->toContain('[data-atom-table-row]')
        ->and($enter)->toContain('box.click()');
});

it('guards a click on a control inside the row with the same selector the href row uses', function () {
    $click = rowsToggleRootAttribute(rowsToggleTable('rows-toggle-when-selected'), 'x-on:click.capture');

    expect($click)->toContain(Attributes::rowGuard('row'))
        ->and($click)->toContain('[role=checkbox]')
        ->and($click)->toContain("['wire:click', 'x-on:click', '@click']");

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
    $html = rowsToggleTable('rows-toggle-when-selected');
    $click = rowsToggleRootAttribute($html, 'x-on:click.capture');
    $enter = rowsToggleRootAttribute($html, 'x-on:keydown.enter.capture');

    expect($click)->toContain('e.detail > 1')
        ->and($click)->toContain("String(window.getSelection()) !== ''")
        ->and($enter)->toContain('e.repeat');
});

it('prints a translated hint escaped', function () {
    app()->setLocale('xx');
    app('translator')->addLines(['messages.untick-to-open-record' => '<b>Untick</b> & go'], 'xx', 'atom');

    $html = rowsToggleTable('rows-toggle-when-selected');

    expect($html)->toContain('&lt;b&gt;Untick&lt;/b&gt; &amp; go')
        ->and($html)->not->toContain('<b>Untick</b>');
});
