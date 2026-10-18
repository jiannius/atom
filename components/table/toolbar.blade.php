@props([
    'trashed' => false,
    'strip' => false,
])

{{-- The bar above a list's rows, for the table's `header` slot: the search and the filters on the start side, then
     whatever sits at the end (a count and a total, an export, the archived toggle). Put it in <x-slot:header> so the
     table swaps it for the bulk-action bar while rows are ticked (the table, not this bar, does that). The default
     slot is the controls: <atom:table.search> (240px wide from sm, full width below), <atom:table.filters> (which
     grows to fill the row and brings its own chips) and any other control. `end` is the trailing group.
     `trashed` adds the archived or voided toggle to it (`true` or `voided`), for a table that does not pass `trashed`
     itself: use one or the other, not both.
     In the table's `header` slot the table draws the strip around it (a frameless table's hairline and padding). Placed
     anywhere else, in a panel above a table, pass `strip` to draw its own: `border-b px-3 py-2.5`. --}}
@php
$hasEnd = isset($end) && $end->isNotEmpty();
@endphp

<div {{ $attributes->class(['flex min-w-0 grow basis-full flex-wrap items-start gap-2', 'border-b px-3 py-2.5' => $strip]) }} data-atom-table-toolbar>
    <div class="flex min-w-0 grow basis-full flex-wrap items-start gap-2 sm:basis-auto [&_[data-atom-table-search]]:w-full sm:[&_[data-atom-table-search]]:w-60" data-atom-table-toolbar-start>
        {{ $slot }}
    </div>

    @if ($hasEnd || $trashed)
        <div class="ms-auto flex min-h-(--atom-control-h) shrink-0 flex-wrap items-center gap-2 text-xs text-fg-subtle" data-atom-table-toolbar-end>
            @if ($hasEnd)
                {{ $end }}
            @endif

            @if ($trashed)
                <atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" />
            @endif
        </div>
    @endif
</div>
