<?php

use Illuminate\Support\ViewErrorBag;

/**
 * #215: the table checkbox is a control for the keyboard and a screen reader, not a bare div. The behaviour is in
 * tests/e2e/table-checkbox-keyboard.spec.js; this holds what the markup says.
 */

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/**
 * The first element with data-atom-table-checkbox in the markup.
 */
function checkboxElement(string $html): DOMElement
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?><table><tr>'.$html.'</tr></table>');
    $found = (new DOMXPath($dom))->query('//*[@data-atom-table-checkbox]');

    expect($found->length)->toBeGreaterThan(0);

    return $found->item(0);
}

it('renders a focusable checkbox with the default name "Select row"', function () {
    $box = checkboxElement(renderBlade('<atom:table.cell checkbox="7" />'));

    expect($box->getAttribute('role'))->toBe('checkbox')
        ->and($box->getAttribute('tabindex'))->toBe('0')
        ->and($box->getAttribute('aria-label'))->toBe('Select row')
        ->and($box->getAttribute('data-checkbox-id'))->toBe('7');
});

it('binds aria-checked to the same expression as data-checked, and renders no static value Livewire could reset', function () {
    $html = renderBlade('<atom:table.cell checkbox="7" />');
    $box = checkboxElement($html);

    expect($box->hasAttribute('aria-checked'))->toBeFalse()
        ->and($box->getAttribute('x-bind:aria-checked'))->toContain('$wire._table.checkboxes.includes(\'7\')')
        ->and($box->getAttribute('x-bind:aria-checked'))->toContain('select_all')
        ->and($box->getAttribute('x-bind:data-checked'))->toContain('$wire._table.checkboxes.includes(\'7\')');
});

it('toggles on Space by clicking itself, so the cell\'s click handler is the one wiring', function () {
    $box = checkboxElement(renderBlade('<atom:table.cell checkbox="7" />'));

    expect($box->getAttribute('x-on:keydown.space.prevent'))->toContain('$el.click()')
        ->and($box->getAttribute('x-on:click'))->toContain('atom.arr.toggle');
});

it('takes a per-row name from checkbox-label, which lands on the checkbox and not on the cell', function () {
    $html = renderBlade('<atom:table.cell checkbox="7" checkbox-label="Select ORD-000031" class="extra" />');
    $box = checkboxElement($html);

    expect($box->getAttribute('aria-label'))->toBe('Select ORD-000031')
        ->and($html)->not->toContain('checkbox-label=')
        ->and(substr_count($html, 'Select ORD-000031'))->toBe(1);
});

it('escapes a per-row name', function () {
    $html = renderBlade('<atom:table.cell checkbox="7" :checkbox-label="$label" />', ['label' => 'Select "><script>x</script>']);

    expect($html)->not->toContain('<script>x</script>');
    expect(checkboxElement($html)->getAttribute('aria-label'))->toBe('Select "><script>x</script>');
});

it('names the header checkbox "Select all" and binds its state', function () {
    $box = checkboxElement(renderBlade('<atom:table.column checkbox />'));

    expect($box->getAttribute('role'))->toBe('checkbox')
        ->and($box->getAttribute('tabindex'))->toBe('0')
        ->and($box->getAttribute('aria-label'))->toBe('Select all')
        ->and($box->getAttribute('x-bind:aria-checked'))->toBe('!!allChecked')
        ->and($box->hasAttribute('aria-checked'))->toBeFalse();
});

it('reads the state of a static checkbox from data-checked', function () {
    $on = checkboxElement(renderBlade('<atom:table.checkbox data-checked />'));
    $off = checkboxElement(renderBlade('<atom:table.checkbox />'));

    expect($on->getAttribute('aria-checked'))->toBe('true')
        ->and($off->getAttribute('aria-checked'))->toBe('false');
});

it('does not read as a value control to the disabled guard (a div with a role, no input, select or textarea)', function () {
    $source = file_get_contents(__DIR__.'/../../components/table/checkbox.blade.php');

    expect(preg_match('/x-modelable|<input|<select|<textarea|contenteditable/', $source))->toBe(0);
});
