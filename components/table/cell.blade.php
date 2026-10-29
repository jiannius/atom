@props([
    'align' => 'left',
    'checkbox' => null,
    'filler' => null,
    'muted' => false,
    'truncate' => false,
    'numeric' => false,
    'column' => null,
])

@php
if ($checkbox) {
    app('atom')->requireTrait(\Jiannius\Atom\Traits\WithTable::class, '<atom:table.cell checkbox>');
}

// `column` links the cell to a named table column, so hiding that column hides this cell. It becomes a CSS token: a value
// outside the pattern throws. A checkbox cell is never linked.
if ($column !== null && ! $checkbox) {
    $attributes = $attributes->merge(['data-atom-column' => \Jiannius\Atom\Services\TableColumns::assertName($column, '<atom:table.cell column>')]);
}

// A numeric cell (money, quantities) is right-aligned with tabular figures, so a column of amounts lines up on the
// decimal point; name the currency in the column header, not here.
$align = $numeric ? 'right' : $align;

// The row's height is a floor, never a fixed height with no padding: the cell keeps its vertical padding, so a
// two-line cell (a name and a 12px line under it) is as tall as it needs and stays clear of its borders.
$classes = Arr::toCssClasses([
    // nowrap is the default, but at zero specificity (modal/menu use the same idiom)
    // so a caller's whitespace-normal, or a responsive md:whitespace-normal, wins.
    'px-row py-row leading-row h-(--atom-row-min-h) [:where(&)]:whitespace-nowrap',
    $muted ? 'text-fg-muted' : 'text-fg',
    'tabular-nums' => $numeric,
    $checkbox ? 'w-10' : '',
    // max-w-0 + w-full: the cell takes the room left over and ellipsises, instead of
    // sizing the column (and the table) to its longest value.
    $truncate ? 'truncate max-w-0 w-full' : '',
    match ($align) {
        'left' => 'text-left justify-start',
        'center' => 'text-center justify-center',
        'right' => 'text-right justify-end',
    },
]);
@endphp

@if ($checkbox)
    <td x-on:click.stop {{ $attributes->class($classes) }}>
        <atom:table.checkbox
        data-checkbox-id="{{ $checkbox }}"
        x-on:click="
            if ($wire._table.select_all) $wire.set('_table.select_all', false)
            atom.arr.toggle($wire._table.checkboxes, {{ js($checkbox) }})
        "
        x-bind:data-checked="$wire._table.select_all || $wire._table.checkboxes.includes({{ js($checkbox) }})" />
    </td>
@else
    <td {{ $attributes->class($classes) }}>
        {{ $slot->isEmpty() ? $filler : $slot }}
    </td>
@endif
