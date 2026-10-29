@props([
    'align' => 'left',
    'sort' => null,
    'checkbox' => false,
    'numeric' => false,
    'name' => null,
    'hideable' => false,
    'hidden' => false,
])

@php
if ($sort || $checkbox) {
    app('atom')->requireTrait(\Jiannius\Atom\Traits\WithTable::class, '<atom:table.column>');
}

// A "raw:" sort has to reach the client signed, or a tampered value could
// splice arbitrary SQL into toTable()'s orderByRaw() — issue #54. Sign it
// here so the value the Alpine handler round-trips through $wire.set() is
// the one the raw: sort gate (AtomServiceProvider's update listener) and toTable() will accept.
$sortValue = $sort && str($sort)->startsWith('raw:')
    ? \Jiannius\Atom\Services\TableSort::sign(str($sort)->after('raw:')->toString())
    : $sort;

// A named column joins the column picker: it is listed there, and the cells that carry a matching `column` follow it. Without
// `hideable` it is locked (listed, always shown). A name becomes a CSS token, so one outside the pattern throws here,
// before it can reach a stylesheet. A checkbox column is never listed. `hidden` means "hidden until the user shows it" only on a
// hideable column; anywhere else it is still the plain HTML attribute.
$named = ! $checkbox && $name !== null;
if ($named) {
    $name = \Jiannius\Atom\Services\TableColumns::assertName($name, '<atom:table.column name>');
}
$hideable = $named && $hideable;

if ($hidden && ! $hideable) {
    $attributes = $attributes->merge(['hidden' => true]);
}

if ($named) {
    $attributes = $attributes->merge([
        'data-atom-column' => $name,
        'data-atom-column-meta' => json_encode(\Jiannius\Atom\Services\TableColumns::meta($name, $slot->toHtml(), $hideable, (bool) $hidden)),
    ]);
}

// A numeric column (money, quantities) lines its figures up on the right; the header follows. Name the currency in the
// header ("Amount (RM)"), not in every cell.
$align = $numeric ? 'right' : $align;

// The header is a quiet 12px label in the cell's own padding, not an uppercase one.
$classes = Arr::toCssClasses([
    'px-row py-2 inline-flex items-center gap-2',
    'text-xs font-medium text-fg-subtle',

    match ($align) {
        'left' => 'justify-start',
        'center' => 'justify-center',
        'right' => 'justify-end',
    },

    $sort ? 'cursor-pointer text-fg underline decoration-dotted underline-offset-4' : '',
]);
@endphp

{{-- The caller's attributes belong on the <th>, not on the layout wrapper
     inside it: a responsive `class="hidden lg:table-cell"` has to hide the
     header cell itself, or the column leaves an empty gap behind. Matches
     table/cell.blade.php, which puts the bag on its <td>.

     Likewise nowrap and the checkbox w-10 live on the <th>: nowrap at zero
     specificity so a caller's whitespace-normal wins (white-space inherits into
     the wrapper), and w-10 because under auto layout a width on the inner
     wrapper is only a hint. --}}
<th {{ $attributes->class([
    'p-0 bg-surface border-b sticky top-0 z-1',
    '[:where(&)]:whitespace-nowrap',
    $checkbox ? 'w-10' : '',
    match ($align) {
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    },
]) }}>
    <div
    @if ($sort)
        x-data="{ sort: @js($sortValue) }"
        x-on:click="() => {
            if ($wire._table.sort.column !== sort) {
                $wire.set('_table.sort.column', sort)
                $wire.set('_table.sort.direction', 'asc')
            }
            else if ($wire._table.sort.direction === 'asc') {
                $wire.set('_table.sort.direction', 'desc')
            }
            else {
                $wire.set('_table.sort.column', null)
                $wire.set('_table.sort.direction', null)
            }
        }"
    @endif
    class="{{ $classes }}">
        <div class="grow">
            @if ($checkbox)
                <atom:table.checkbox
                x-data="{
                    get rowCheckboxes () {
                        return [...this.$el.closest('table').querySelectorAll('tbody [data-atom-table-checkbox]')]
                    },

                    // ids of the rows on the CURRENT page (data attr, not the
                    // reactive data-checked — avoids a read race on toggle)
                    get pageIds () {
                        return this.rowCheckboxes.map(cb => cb.dataset.checkboxId).filter(id => id != null)
                    },

                    // checked when every current-page row is selected, so the
                    // indicator no longer lies across pages (it used to compare
                    // the total selected count to the current page's row count)
                    get allChecked () {
                        if ($wire._table.select_all) return true
                        let selected = ($wire._table.checkboxes || []).map(String)
                        return this.pageIds.length > 0 && this.pageIds.every(id => selected.includes(String(id)))
                    },

                    toggle () {
                        // leaving select-all clears everything in one go
                        if ($wire._table.select_all) return $wire.resetTableCheckboxes()

                        let target = !this.allChecked
                        this.rowCheckboxes.forEach(cb => {
                            let isChecked = cb.hasAttribute('data-checked')
                            if ((target && !isChecked) || (!target && isChecked)) cb.click()
                        })
                    },
                }"
                x-on:click="toggle()"
                x-bind:data-checked="allChecked" />
            @else
                {{ $slot }}
            @endif
        </div>

        @if ($sort)
            <div x-show="$wire._table.sort.column" class="shrink-0 flex items-center justify-center text-fg-subtle size-3">
                <atom:icon.arrow-down class="size-3" x-show="$wire._table.sort.column === '{{ $sortValue }}' && $wire._table.sort.direction === 'asc'"/>
                <atom:icon.arrow-up class="size-3" x-show="$wire._table.sort.column === '{{ $sortValue }}' && $wire._table.sort.direction === 'desc'"/>
            </div>
        @endif
    </div>
</th>
