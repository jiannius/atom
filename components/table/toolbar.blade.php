@props([
    'trashed' => false,
    'strip' => false,
    'count' => true,
])

{{-- The bar above a list's rows, for the table's `header` slot: the search and the filters on the start side, then
     the row count and whatever else sits at the end (an export, the archived toggle). Put it in <x-slot:header> so the
     table swaps it for the bulk-action bar while rows are ticked (the table, not this bar, does that). The default
     slot is the controls: <atom:table.search> (240px wide from sm, full width below), <atom:table.filters> (which
     grows to fill the row and brings its own chips) and any other control. `end` is the trailing group.
     `trashed` adds the archived or voided toggle to it (`true` or `voided`). Use it OR the table's own `trashed` prop, never
     both: with both the toggle is drawn twice.
     The row count ("1–50 of 128", from the table's paginator; `:count="false"` leaves it out) leads the end group. The header slot
     is an Alpine template, and text the server renders into it is not refreshed by a re-render, so the count is not rendered here:
     the table prints it outside the template, on a hidden element a morph keeps current, and this group reads it (`tableCount`,
     resources/js/alpinejs/table-count.js) when it starts and after every morph. Any other count or total of your own belongs below
     the table, for the same reason. Outside a table, or in a table with no paginator, nothing shows.
     In the table's `header` slot the table draws the strip around it (a frameless table's hairline and padding). Placed
     anywhere else, in a panel above a table, pass `strip` to draw its own: `border-b px-3 py-2.5`.
     On a phone the start group takes the full row, unless there is an `end` group (or `trashed`): then the start group has a 12rem floor: a narrow end group (an icon-only button,
     with `max-sm:sr-only` on its label) shares the first row, a wide one wraps to a row of its own, and the search is never squeezed under 12rem.
     From `sm` up the start group takes only what the end group leaves (`flex: 1 1 0`, so it never asks for a row of its own) and wraps its
     chips inside itself: the end group stays at the top end, on the first row, however many rows the filters take. It keeps a 12rem floor
     (`sm:min-w-48`), measured in the toolbar's own box, not the screen's: in a narrow container (a sidebar column, a slide-over, a modal) a
     wide end group wraps under the start group instead of squeezing the search. The row count is hidden on a phone (the pager shows the
     range there), so it never takes the room an icon-only end group shares with the search. --}}
@php
$hasEnd = isset($end) && $end->hasActualContent();
$hasCount = (bool) $count;
@endphp

<div {{ $attributes->class(['flex min-w-0 grow basis-full flex-wrap items-start gap-2', 'border-b px-3 py-2.5' => $strip]) }} data-atom-table-toolbar>
    <div @class(['flex min-w-0 grow flex-wrap items-start gap-2 sm:basis-0 sm:min-w-48 [&_[data-atom-table-search]]:w-full sm:[&_[data-atom-table-search]]:w-60', 'basis-full' => ! ($hasEnd || $trashed), 'basis-48' => $hasEnd || $trashed]) data-atom-table-toolbar-start>
        {{ $slot }}
    </div>

    @if ($hasEnd || $trashed || $hasCount)
        {{-- the end group has the row count first (text only a paginated table fills in), then the host's own end slot. With nothing else in it
             the group shows only while there is a count, and not on a phone, where the pager says the same (the count itself is `max-sm:hidden` too). --}}
        <div
        @if ($hasCount) x-data="tableCount" @endif
        @if ($hasCount && ! ($hasEnd || $trashed)) x-show="text" x-cloak @endif
        @class(['ms-auto flex min-h-(--atom-control-h) shrink-0 flex-wrap items-center gap-2 self-start text-xs text-fg-subtle', 'max-sm:hidden' => ! ($hasEnd || $trashed)])
        data-atom-table-toolbar-end>
            @if ($hasCount)
                <span x-show="text" x-cloak x-text="text" class="tabular-nums text-xs text-fg-muted max-sm:hidden" data-atom-table-count></span>
            @endif

            @if ($hasEnd)
                {{ $end }}
            @endif

            @if ($trashed)
                <atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" />
            @endif
        </div>
    @endif
</div>
