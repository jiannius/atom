@props([
    'label' => null,
    'value' => null,
    'total' => false,
    'column' => null,
])

@php
// One line of the totals under a table's rows (subtotal, tax, the total): a `<tr>` for the `footer` slot. The cell spans every column and
// puts its label and figure on the table's end edge, so the figure sits under a `numeric` LAST column, and a column the user hides takes
// nothing out of line (the span has room to spare, see atom.css). `column` names the amount column (`<atom:table.column name="amount">`):
// the line follows it when that is hidden, the way a cell with `column` does. `total` is the grand total (semibold, a hairline above).
if ($column !== null) {
    $attributes = $attributes->merge(['data-atom-column' => \Jiannius\Atom\Services\TableColumns::assertName($column, '<atom:table.totals column>')]);
}

// the figure is the slot when it has content, else `value`; a blank one draws the missing-value mark. 0 is a figure.
$hasSlot = $slot->hasActualContent();
$blank = ! $hasSlot && ($value === null || $value === false || trim((string) $value) === '');

$classes = Arr::toCssClasses([
    'px-row py-1 leading-row text-fg',
    'border-t pt-2 font-semibold' => $total,
]);
@endphp

<tr data-atom-table-totals @if ($total) data-atom-table-totals-total @endif>
    <td colspan="100" {{ $attributes->class($classes) }}>
        <div data-atom-totals-line>
            <span data-atom-totals-label @class(['text-fg-muted' => ! $total])>{{ t($label) }}</span>
            <span data-atom-totals-value>@if ($hasSlot){{ $slot }}@elseif ($blank)<atom:_missing />@else{{ $value }}@endif</span>
        </div>
    </td>
</tr>
