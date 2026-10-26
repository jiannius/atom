@props([
    'empty' => null,
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'skeleton' => false,
    'trashed' => false,
    'selectAll' => false,
    'stickySelection' => false,
    'frameless' => false,
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
    {{-- The row count the toolbar shows at its end ("1–50 of 128"). The toolbar is in the header slot, an Alpine template that a
         re-render never refreshes, so the text is printed here, outside it, where a morph keeps it current, and the toolbar reads it
         (resources/js/alpinejs/table-count.js). Nothing is printed without a paginator, or with no rows on it. --}}
    @if ($paginate?->firstItem())
        <span hidden data-atom-table-count-source data-text="{{ t('atom::pagination.range', ['from' => $paginate->firstItem(), 'to' => $paginate->lastItem(), 'total' => $paginate->total()]) }}"></span>
    @endif

    @if (isset($checked) && $checked->isNotEmpty())
        <template x-if="$wire._table.checkboxes.length || $wire._table.select_all" hidden>
            <div class="{{ $checkedClasses }}" data-atom-table-checked @if ($checkedAccent) data-tone="accent" @endif>
                <div class="shrink-0 flex items-center gap-2 text-sm font-medium {{ $checkedMuted }}">
                    <atom:icon.double-check class="size-4"/>
                    <div>
                        <span x-text="$wire._table.select_all ? {{ $total }} : $wire._table.checkboxes.length"></span> {{ t('atom::messages.selected') }}
                    </div>
                </div>

                @if ($canSelectAll)
                    {{-- offer cross-page select-all once a subset is chosen, then a way back out --}}
                    <template x-if="!$wire._table.select_all && $wire._table.checkboxes.length < {{ $total }}" hidden>
                        <button type="button" wire:click="selectAllTableMatching" class="shrink-0 text-sm font-medium text-accent-ink hover:underline" data-atom-table-select-all>
                            {{ t('atom::messages.select-all') }} {{ number_format($total) }}
                        </button>
                    </template>

                    <template x-if="$wire._table.select_all" hidden>
                        <button type="button" wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium {{ $checkedMuted }} hover:underline">
                            {{ t('atom::messages.deselect-all') }}
                        </button>
                    </template>
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
                    <template x-if="!$wire._table.select_all" hidden>
                        <button type="button" wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium {{ $checkedMuted }} hover:underline" data-atom-table-clear-selection>
                            {{ t('atom::messages.clear-selection') }}
                        </button>
                    </template>
                @endif

                <div {{ $checked->attributes->except('tone')->class('grow flex flex-wrap items-center gap-3') }}>
                    {{ $checked }}
                </div>
            </div>
        </template>
    @endif

    @if (isset($header) || $trashed)
        <template x-if="{!! $showHeader !!}" hidden>
            @isset ($header)
                <div {{ $header->attributes->class(['min-h-10', $header->attributes->get('class', 'flex flex-wrap items-center gap-3'), $stripClasses]) }}>
                    {{ $header }}

                    @if ($trashed)
                        <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
                    @endif
                </div>
            @else
                <div class="{{ Arr::toCssClasses(['min-h-10 flex flex-wrap items-center gap-3', $stripClasses]) }}">
                    <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
                </div>
            @endisset
        </template>
    @endif
    
    <div class="relative min-w-0">
        <div @class([
            'min-w-0 divide-y divide-border',
            'overflow-hidden rounded-panel border bg-surface' => ! $frameless,
        ])>
            {{-- the scroll box: a wide table scrolls sideways in here, never the page (min-w-0 on every ancestor up to the root,
                 or a grid or flex parent such as <atom:page> grows to the table's width instead) --}}
            <div class="relative min-w-0 overflow-x-auto" data-atom-table-scroll>
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
                <atom:table.pagination :paginate="$paginate" :max-rows="$maxRows" />
            @endif
        </div>

        {{-- The overlay is a sibling of the card, not a child of the table's scroll
             box, because the spinner inside it is sticky: sticky tracks the nearest
             scroll container, and both `overflow-hidden` (the card, for its rounded
             corners) and `overflow-x-auto` (the table, for wide columns) are scroll
             containers whose height never exceeds their content — so from inside
             either one the spinner has nothing to stick to and never moves.

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

