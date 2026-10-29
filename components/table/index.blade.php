@props([
    'empty' => null,
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'skeleton' => false,
    'trashed' => false,
    'selectAll' => false,
    'stickySelection' => false,
    'frameless' => false,
    'stickyPager' => true,
    'persistKey' => null,
    'columnsState' => null,
])

@php
app('atom')->requireTrait(\Jiannius\Atom\Traits\WithTable::class, '<atom:table>');

// First-load skeleton: opt-in, and only while a paginator hasn't loaded yet.
// Gated behind $skeleton so static/synchronous tables are completely unaffected.
$showSkeleton = $skeleton && is_null($paginate);
$skeletonRows = $skeleton === true ? 5 : (int) $skeleton;

// "Select all matching" (cross-page) is opt-in and needs a paginator to know
// the total — the consumer also wires a tableQuery() for $this->tableSelection().
$total = (int) ($paginate?->total() ?? 0);
$canSelectAll = $selectAll && $paginate;

// A filter change swaps the result set out from under the selection. By default
// that clears it, because a bulk action over rows the user can no longer see is
// a surprise. With `sticky-selection` the checked ids are kept instead, so a
// user can build one batch across several searches — the component then owes a
// tableSelectionQuery() (see Traits\WithTable) or those ids resolve to
// nothing. select_all always goes: it means "everything matching *this* query",
// which stops being true the moment the query changes.
$onFilterChanged = $stickySelection
    ? 'if ($wire._table?.select_all) $wire.clearTableSelectAll()'
    : 'if ($wire._table?.checkboxes?.length || $wire._table?.select_all) $wire.resetTableCheckboxes()';

// The checked bar normally takes the header's place while a selection exists —
// fine when the selection dies at the next filter change anyway. A sticky table
// has to keep the search and filters reachable instead: holding a selection
// *while you go on searching* is the entire point, and a swapped-out header
// makes the flow impossible. The two bars stack there.
$showHeader = $stickySelection ? 'true' : '!$wire._table.checkboxes.length';

// `frameless` is for a table that sits inside a panel the page already draws (a list with saved views and a toolbar
// above it): no outer box of its own, the header and checked bars become hairline strips, and the rows keep their
// hairlines. The framed table (the default) is the same table in its own panel.
$frameless = (bool) $frameless;

$stripClasses = $frameless ? 'border-b px-3 py-2.5' : '';

$checkedAccent = isset($checked) && $checked->attributes->get('tone') === 'accent';
$checkedClasses = Arr::toCssClasses([
    'min-h-10 flex flex-wrap items-center gap-3',
    $stripClasses,
    'bg-accent-soft' => $checkedAccent && $frameless,
    'bg-accent-soft rounded-panel px-3.5 py-2' => $checkedAccent && ! $frameless,
]);
$checkedMuted = $checkedAccent ? 'text-accent-ink' : 'text-fg-muted';

if (!$showSkeleton && !is_bool($empty)) {
    if ($paginate) $empty = !$paginate->total();
    else $empty = isset($rows) && !strip_tags($rows->toHtml());
}

// Column visibility (resources/js/helpers/table-columns.js). The named columns come off the rendered `columns` slot, which Blade
// has captured before this view runs, so they exist while the table is empty or in its skeleton, when there is no <thead> to read.
// `columns-state` is the host's own copy of the user's choices (name => visible), read from the user's settings: when it is given it
// is used instead of localStorage. Without it a `persist-key` keeps the choices in localStorage; with neither they live in memory.
$columnsList = isset($columns) ? \Jiannius\Atom\Services\TableColumns::payload($columns->toHtml()) : [];
$columnsKey = filled($persistKey) ? (string) $persistKey : null;
$columnsFromHost = $columnsState !== null;
$columnsChosen = $columnsFromHost
    ? \Jiannius\Atom\Services\TableColumns::cleanState($columnsState instanceof \Illuminate\Contracts\Support\Arrayable ? $columnsState->toArray() : $columnsState)
    : [];
$columnsHidden = \Jiannius\Atom\Services\TableColumns::hiddenNames($columnsList, $columnsChosen);
$columnsCss = \Jiannius\Atom\Services\TableColumns::css($columnsList);
$columnsScript = $columnsCss !== '' && $columnsKey !== null && ! $columnsFromHost;
@endphp

{{-- The checked bar wraps (count first, then the actions) so a narrow screen shows every action instead of clipping them.
     It is untinted by default. `<x-slot:checked tone="accent">` tints it with the accent-soft token and sets its text
     and icons in the accent ink, so the selected state stands out (any other tone is the default). Framed, the bar is its own
     rounded strip; frameless it is a square strip inside the panel you draw, with its hairline strip padding, like the untinted one. --}}
<div
{{ $attributes->merge(['x-data' => '{}'])->class(['group/table min-w-0 max-w-full', 'space-y-4' => ! $frameless]) }}
x-on:table-filter:changed.window="{!! $onFilterChanged !!}"
data-atom-table
@if ($frameless) data-atom-table-frameless @endif>
    {{-- The row count the toolbar shows at its end ("1–50 of 128"). The toolbar is in the header slot, which is hidden while
         rows are ticked (and can be re-rendered on its own), so the text is printed here, outside it, where a morph keeps it current, and
         the toolbar reads it (resources/js/alpinejs/table-count.js). Nothing is printed without a paginator, or with no rows on it. --}}
    @if ($paginate?->firstItem())
        <span hidden data-atom-table-count-source data-text="{{ t('atom::pagination.range', ['from' => $paginate->firstItem(), 'to' => $paginate->lastItem(), 'total' => $paginate->total()]) }}"></span>
    @endif

    {{-- The named columns, for the column picker and the browser's store: a hidden source the way the count is, printed here because
         the picker may live in the header slot (hidden while rows are ticked) or outside the table. The stylesheet has one rule per
         hideable column, keyed on a token list on the scroll box; both are printed before the first visible child (a trailing
         hidden child would take a space-y margin). Every name in them has passed TableColumns::NAME. --}}
    @if ($columnsList)
        <span hidden data-atom-column-source x-init="atom.tableColumns.sync($el)" data-columns="{{ json_encode($columnsList) }}"
            @if ($columnsKey !== null) data-key="{{ $columnsKey }}" @endif
            @if ($columnsFromHost) data-state="{{ json_encode((object) $columnsChosen) }}" @endif></span>
    @endif

    @if ($columnsCss !== '')
        <style data-atom-column-css>{!! $columnsCss !!}</style>
    @endif

    {{-- The checked bar and the header are real elements shown with x-show, never a `<template x-if>` around the server-rendered slot: from
         Alpine 3.17 (Livewire 4.4) the morph skips what an x-if rendered, so a header or `checked` slot that changes on the server (an `@if`,
         a select that depends on another filter's value, a count) would never update. A hidden bar is `display: none`, so nothing in it can be
         focused or tabbed to. The checked bar has `x-cloak` (hidden until Alpine says a row is ticked); the header has NOT: it is shown
         at first paint, as the server rendered it, and its components start while it is visible (under `x-cloak` they would measure
         themselves at zero size, the filter chips and the popovers' first placement among them). The total an expression needs rides on a data attribute (`data-total`), which a morph rewrites in place but which no
         expression tracks: the count and the select-all button each hold it as reactive state (`x-data="tableChecked"`, resources/js/alpinejs/
         table-checked.js, which watches the attribute), and the scope is theirs alone, not the bar's, so the host's `checked` slot is
         not inside it. --}}
    @if (isset($checked) && $checked->isNotEmpty())
        <div x-show="$wire._table.checkboxes.length || $wire._table.select_all" x-cloak class="{{ $checkedClasses }}" data-atom-table-checked @if ($checkedAccent) data-tone="accent" @endif data-total="{{ $total }}">
            <div class="shrink-0 flex items-center gap-2 text-sm font-medium {{ $checkedMuted }}">
                <atom:icon.double-check class="size-4"/>
                <div x-data="tableChecked">
                    <span x-text="$wire._table.select_all ? _atomTotal : $wire._table.checkboxes.length"></span> {{ t('atom::messages.selected') }}
                </div>
            </div>

            @if ($canSelectAll)
                {{-- offer cross-page select-all once a subset is chosen, then a way back out --}}
                <button type="button" x-data="tableChecked" x-show="!$wire._table.select_all && $wire._table.checkboxes.length < _atomTotal" x-cloak wire:click="selectAllTableMatching" class="shrink-0 text-sm font-medium text-accent-ink hover:underline" data-atom-table-select-all>
                    {{ t('atom::messages.select-all') }} {{ number_format($total) }}
                </button>

                <button type="button" x-show="$wire._table.select_all" x-cloak wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium {{ $checkedMuted }} hover:underline">
                    {{ t('atom::messages.deselect-all') }}
                </button>
            @endif

            @if ($stickySelection)
                {{-- the batch is the thing being built, so it needs to be
                     reviewable: this lists the selection instead of the filtered
                     rows, ignoring the search that hid half of it. --}}
                <button type="button" wire:click="toggleTableShowSelected" class="shrink-0 text-sm font-medium text-accent-ink hover:underline" data-atom-table-show-selected>
                    <span x-show="!$wire._table.show_selected">{{ t('atom::messages.show-selected') }}</span>
                    <span x-show="$wire._table.show_selected" x-cloak>{{ t('atom::messages.show-all') }}</span>
                </button>

                {{-- part of the selection can be off-screen once it outlives a
                     filter, so the way out can't depend on finding the ticked
                     rows again. Hidden in select-all mode, which brings its own. --}}
                <button type="button" x-show="!$wire._table.select_all" wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium {{ $checkedMuted }} hover:underline" data-atom-table-clear-selection>
                    {{ t('atom::messages.clear-selection') }}
                </button>
            @endif

            <div {{ $checked->attributes->except('tone')->class('grow flex flex-wrap items-center gap-3') }}>
                {{ $checked }}
            </div>
        </div>
    @endif

    @if (isset($header) || $trashed)
        @isset ($header)
            <div x-show="{!! $showHeader !!}" {{ $header->attributes->class(['min-h-10', $header->attributes->get('class', 'flex flex-wrap items-center gap-3'), $stripClasses]) }}>
                {{ $header }}

                @if ($trashed)
                    <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
                @endif
            </div>
        @else
            <div x-show="{!! $showHeader !!}" class="{{ Arr::toCssClasses(['min-h-10 flex flex-wrap items-center gap-3', $stripClasses]) }}">
                <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
            </div>
        @endisset
    @endif

    {{-- The pager is the last child of this box, which is why the box clips (`overflow-clip`) instead of hiding its overflow: `overflow-hidden`
         makes it a scroll container, and a sticky pager inside would stick to a box that never scrolls instead of to the page. The
         scroll box below is the only scroller, and the pager sits outside it, so a wide table still scrolls sideways under a pager that
         stays put. --}}
    <div class="relative min-w-0">
        <div @class([
            'min-w-0 divide-y divide-border',
            'overflow-clip rounded-panel border bg-surface' => ! $frameless,
        ])>
            {{-- the scroll box: a wide table scrolls sideways in here, never the page (min-w-0 on every ancestor up to the root,
                 or a grid or flex parent such as <atom:page> grows to the table's width instead) --}}
            <div class="relative min-w-0 overflow-x-auto" data-atom-table-scroll
                @if ($columnsList) data-atom-column-scope @endif
                @if ($columnsCss !== '') data-atom-column-hide="{{ implode(' ', $columnsHidden) }}" @endif
                @if ($columnsList && $columnsKey !== null) data-atom-column-key="{{ $columnsKey }}" @endif>
                {{-- First paint: the user's choices from localStorage, written before the <table> below is parsed, so a column the user
                     hid is never drawn and a default-hidden one they showed is never missing. The text is constant (TableColumns::SCRIPT). --}}
                @if ($columnsScript)
                    <script{!! ($nonce = \Illuminate\Support\Facades\Vite::cspNonce()) ? ' nonce="'.e($nonce).'"' : '' !!}>{!! \Jiannius\Atom\Services\TableColumns::SCRIPT !!}</script>
                @endif
                @if ($showSkeleton)
                    <div class="motion-safe:animate-pulse divide-y divide-border" data-atom-table-skeleton>
                        @for ($i = 0; $i < $skeletonRows; $i++)
                            <div class="py-4 px-4" data-atom-table-skeleton-row>
                                <atom:placeholder-bar size="{{ [45, 70, 55, 80, 50][$i % 5] }}%x10" />
                            </div>
                        @endfor
                    </div>
                @elseif ($empty)
                    <atom:empty />
                @else
                    <table class="min-w-full text-fg divide-y divide-border">
                        @if (isset($columns) && $columns->isNotEmpty())
                            <thead data-atom-table-columns>
                                <tr {{ $columns->attributes }}>
                                    {{ $columns }}
                                </tr>
                            </thead>
                        @endif

                        @if (isset($rows) && $rows->isNotEmpty())
                            <tbody {{ $rows->attributes->class(['divide-y divide-border']) }} data-atom-table-rows>
                                {{ $rows }}
                            </tbody>
                        @endif

                        @if (isset($footer) && $footer->isNotEmpty())
                            <tfoot {{ $footer->attributes }} data-atom-table-footer>
                                {{ $footer }}
                            </tfoot>
                        @endif
                    </table>
                @endif
            </div>

            @if ($paginate?->hasPages())
                <atom:table.pagination :paginate="$paginate" :max-rows="$maxRows" :sticky="$stickyPager" />
            @endif
        </div>

        {{-- The overlay is a sibling of the card, not a child of the table's scroll
             box, because the spinner inside it is sticky: sticky tracks the nearest
             scroll container, and `overflow-x-auto` (the table, for wide columns) is
             one whose height never exceeds its content — so from inside it the
             spinner has nothing to stick to and never moves. (The card clips its
             rounded corners with `overflow-clip`, which is not a scroll container,
             so the pager can stick to the page.)

             A consequence worth knowing: the veil now covers the pagination bar,
             which it did not when it lived inside the scroll box. Prev/next and
             rows-per-page are unclickable while a load is in flight.

             What holds this element down at rest is the `[wire:loading]` rule in
             resources/css/atom.css. Livewire ships the same rule, but only injects
             its stylesheet on a request that actually rendered a component — so a
             plain-Blade table on a Livewire-free page had nothing hiding it. Do NOT
             reach for the `hidden` attribute instead: Tailwind's Preflight hides
             `[hidden]` with `!important`, which outranks the inline display Livewire
             sets, and the overlay would then never appear at all.

             Targets are every atom-owned control that swaps the result set out.
             Search is deliberately absent: it spins in its own input instead
             (components/table/search.blade.php) and leaves the rows readable.
             Consumer-owned filter properties can't be named from here. --}}
        <div
        wire:loading.flex
        wire:target="gotoPage,nextPage,previousPage,_table.sort.column,_table.sort.direction,_table.max_rows,_table.show_trashed"
        class="{{ Arr::toCssClasses(['absolute inset-0 z-10 justify-center bg-surface/60', 'rounded-panel' => ! $frameless]) }}"
        data-atom-table-loading>
            {{-- Two stacked constraints, because one is not enough. The strut is a
                 viewport-tall box capped to the table, so centring in it lands the
                 spinner in the middle of the visible slice of a long table, and in
                 the middle of a short one. But a sticky box can never leave its own
                 containing block, so where the table only partly overlaps the screen
                 the strut cannot reach the visible part — which is why the spinner
                 carries `top`/`bottom` of its own. It is small enough to move. --}}
            <div class="sticky top-[var(--atom-shell-bar-h,0px)] h-[calc(100dvh-var(--atom-shell-bar-h,0px))] max-h-full flex items-center">
                <atom:icon.loading class="sticky top-[calc(var(--atom-shell-bar-h,0px)+1rem)] bottom-4 size-6 text-fg-muted" />
            </div>
        </div>
    </div>
</div>

