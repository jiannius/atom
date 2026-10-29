@props([
    'trashed' => false,
    'strip' => false,
    'count' => true,
    'columns' => false,
])

{{-- The bar above a list's rows, for the table's `header` slot: the search and the filters on the start side, then
     the row count and whatever else sits at the end (an export, the archived toggle). Put it in <x-slot:header> so the
     table swaps it for the bulk-action bar while rows are ticked (the table, not this bar, does that). The default
     slot is the controls: <atom:table.search> (240px wide from sm, full width below), <atom:table.filters> (which
     grows to fill the row and brings its own chips) and any other control. The `actions` slot is the trailing group: the
     host's own buttons (an export, an import). `end` is the same place under its older name and keeps working all through 4.x: both
     print, `actions` first. The `menu` slot holds <atom:menu.item>s for the overflow menu: atom draws the ⋯ ghost button (tooltip "More
     actions") and the dropdown, aligned to the end, as <atom:page.header> does for its own `menu` slot; no content, no button.
     `trashed` adds the archived or voided toggle to the end group (`true` or `voided`). Use it OR the table's own `trashed` prop, never
     both: with both the toggle is drawn twice.
     `columns` adds the column picker (<atom:table.columns>) last in the end group, after a thin divider drawn only when something visible
     precedes it (with only the row count before it, the divider follows the count: no text, no divider, and none on a phone); `columns="table-id"`
     passes the id as the picker's `for`, for a toolbar that sits outside the table it steers. It is opt-in: the toolbar renders before the
     table's columns, so it cannot tell whether any is `hideable`.
     Order of the end group: the row count, `actions`, `end`, the trashed toggle, the ⋯ menu, the divider and the column picker. A slot counts as content only if it holds more
     than comments and whitespace (hasActualContent), so a slot of `@if` blocks that all come out false draws nothing.
     The row count ("1–50 of 128", from the table's paginator; `:count="false"` leaves it out) leads the end group. The table's header slot
     is a real element the server keeps current, but the paginator belongs to the table, not to this bar, so the count is not rendered here:
     the table prints it on a hidden element a morph keeps current, and this group reads it (`tableCount`,
     resources/js/alpinejs/table-count.js) when it starts and after every morph. Any other count or total of your own belongs below
     the table. Outside a table, or in a table with no paginator, nothing shows.
     In the table's `header` slot the table draws the strip around it (a frameless table's hairline and padding). Placed
     anywhere else, in a panel above a table, pass `strip` to draw its own: `border-b px-3 py-2.5`.
     On a phone the start group takes the full row, unless there is an end group (`actions`, `end`, `menu`, `trashed` or `columns`): then the start group has a 12rem floor: a narrow end group (an icon-only button,
     with `max-sm:sr-only` on its label) shares the first row, a wide one wraps to a row of its own, and the search is never squeezed under 12rem.
     From `sm` up the start group takes only what the end group leaves (`flex: 1 1 0`, so it never asks for a row of its own) and wraps its
     chips inside itself: the end group stays at the top end, on the first row, however many rows the filters take. It keeps a 12rem floor
     (`sm:min-w-48`), measured in the toolbar's own box, not the screen's: in a narrow container (a sidebar column, a slide-over, a modal) a
     wide end group wraps under the start group instead of squeezing the search. The row count is hidden on a phone (the pager shows the
     range there), so it never takes the room an icon-only end group shares with the search. --}}
@php
$has = fn ($slot) => isset($slot) && $slot->hasActualContent();
$hasActions = $has($actions ?? null);
$hasEnd = $has($end ?? null);
$hasMenu = $has($menu ?? null);
$hasColumns = (bool) $columns;
$hasGroup = $hasActions || $hasEnd || $hasMenu || $hasColumns;
$hasCount = (bool) $count;
// what the picker's divider follows: the host's buttons, the trashed toggle and the ⋯ menu are always there when set; the row count
// only while it has text (and not on a phone), so with the count alone the divider takes the count's own rules
$hasBeforeColumns = $hasActions || $hasEnd || $hasMenu || $trashed;
@endphp

<div {{ $attributes->class(['flex min-w-0 grow basis-full flex-wrap items-start gap-2', 'border-b px-3 py-2.5' => $strip]) }} data-atom-table-toolbar>
    <div @class(['flex min-w-0 grow flex-wrap items-start gap-2 sm:basis-0 sm:min-w-48 [&_[data-atom-table-search]]:w-full sm:[&_[data-atom-table-search]]:w-60', 'basis-full' => ! ($hasGroup || $trashed), 'basis-48' => $hasGroup || $trashed]) data-atom-table-toolbar-start>
        {{ $slot }}
    </div>

    @if ($hasGroup || $trashed || $hasCount)
        {{-- the end group has the row count first (text only a paginated table fills in), then the host's own `actions` and `end` slots, the trashed toggle and the ⋯ menu. With nothing else in it
             the group shows only while there is a count, and not on a phone, where the pager says the same (the count itself is `max-sm:hidden` too). --}}
        <div
        @if ($hasCount) x-data="tableCount" @endif
        @if ($hasCount && ! ($hasGroup || $trashed)) x-show="text" x-cloak @endif
        @class(['ms-auto flex min-h-(--atom-control-h) shrink-0 flex-wrap items-center gap-2 self-start text-xs text-fg-subtle', 'max-sm:hidden' => ! ($hasGroup || $trashed)])
        data-atom-table-toolbar-end>
            @if ($hasCount)
                <span x-show="text" x-cloak x-text="text" class="tabular-nums text-xs text-fg-muted max-sm:hidden" data-atom-table-count></span>
            @endif

            @if ($hasActions)
                {{ $actions }}
            @endif

            @if ($hasEnd)
                {{ $end }}
            @endif

            @if ($trashed)
                <atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" />
            @endif

            @if ($hasMenu)
                <atom:dropdown position="bottom" align="end">
                    <atom:button variant="ghost" icon="more" tooltip="More actions" />

                    <atom:menu class="w-[220px]" popover>
                        {{ $menu }}
                    </atom:menu>
                </atom:dropdown>
            @endif

            @if ($hasColumns)
                @if ($hasBeforeColumns || $hasCount)
                    <span @if (! $hasBeforeColumns) x-show="text" x-cloak @endif @class(['h-5 w-px shrink-0 bg-border', 'max-sm:hidden' => ! $hasBeforeColumns]) aria-hidden="true" data-atom-table-toolbar-divider></span>
                @endif

                <atom:table.columns :for="is_string($columns) ? $columns : null" />
            @endif
        </div>
    @endif
</div>
