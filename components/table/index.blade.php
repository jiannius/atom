@props([
    'empty' => null,
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'skeleton' => false,
    'trashed' => false,
    'selectAll' => false,
    'stickySelection' => false,
])

@php
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
// tableSelectionQuery() (see Traits\AtomComponent) or those ids resolve to
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

if (!$showSkeleton && !is_bool($empty)) {
    if ($paginate) $empty = !$paginate->total();
    else $empty = isset($rows) && !strip_tags($rows->toHtml());
}
@endphp

<div
x-data="{}"
x-on:table-filter:changed.window="{!! $onFilterChanged !!}"
class="group/table space-y-4" data-atom-table>
    @if (isset($checked) && $checked->isNotEmpty())
        <template x-if="$wire._table.checkboxes.length || $wire._table.select_all" hidden>
            <div class="min-h-10 flex items-center gap-3" data-atom-table-checked>
                <div class="shrink-0 flex items-center gap-2 text-sm font-medium text-zinc-400">
                    <atom:icon.double-check class="size-5"/>
                    <div>
                        <span x-text="$wire._table.select_all ? {{ $total }} : $wire._table.checkboxes.length"></span> {{ t('atom::messages.selected') }}
                    </div>
                </div>

                @if ($canSelectAll)
                    {{-- offer cross-page select-all once a subset is chosen, then a way back out --}}
                    <template x-if="!$wire._table.select_all && $wire._table.checkboxes.length < {{ $total }}" hidden>
                        <button type="button" wire:click="selectAllTableMatching" class="shrink-0 text-sm font-medium text-primary hover:underline" data-atom-table-select-all>
                            {{ t('atom::messages.select-all') }} {{ number_format($total) }}
                        </button>
                    </template>

                    <template x-if="$wire._table.select_all" hidden>
                        <button type="button" wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium text-zinc-500 hover:underline">
                            {{ t('atom::messages.deselect-all') }}
                        </button>
                    </template>
                @endif

                @if ($stickySelection)
                    {{-- part of the selection can be off-screen once it outlives a
                         filter, so the way out can't depend on finding the ticked
                         rows again. Hidden in select-all mode, which brings its own. --}}
                    <template x-if="!$wire._table.select_all" hidden>
                        <button type="button" wire:click="resetTableCheckboxes" class="shrink-0 text-sm font-medium text-zinc-500 hover:underline" data-atom-table-clear-selection>
                            {{ t('atom::messages.clear-selection') }}
                        </button>
                    </template>
                @endif

                <div class="grow flex items-center gap-3">
                    {{ $checked }}
                </div>
            </div>
        </template>
    @endif

    @if (isset($header) || $trashed)
        <template x-if="{!! $showHeader !!}" hidden>
            @isset ($header)
                <div {{ $header->attributes->class(['min-h-10', $header->attributes->get('class', 'flex flex-wrap items-center gap-3')]) }}>
                    {{ $header }}

                    @if ($trashed)
                        <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
                    @endif
                </div>
            @else
                <div class="min-h-10 flex flex-wrap items-center gap-3">
                    <div class="ml-auto shrink-0"><atom:table.trashed :variant="is_string($trashed) ? $trashed : 'archived'" /></div>
                </div>
            @endisset
        </template>
    @endif
    
    <div class="overflow-hidden rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 shadow-xs divide-y divide-zinc-200 dark:divide-zinc-700">
        <div class="relative overflow-x-auto">
            <div
            wire:loading.flex
            wire:target="gotoPage,nextPage,previousPage,_table.sort.column,_table.sort.direction"
            class="absolute inset-0 z-10 items-center justify-center bg-white/60 dark:bg-zinc-800/60">
                <atom:icon.loading class="size-6 text-zinc-500" />
            </div>

            @if ($showSkeleton)
                <div class="animate-pulse divide-y divide-zinc-200 dark:divide-zinc-700" data-atom-table-skeleton>
                    @for ($i = 0; $i < $skeletonRows; $i++)
                        <div class="py-4 px-4" data-atom-table-skeleton-row>
                            <atom:placeholder-bar size="{{ [45, 70, 55, 80, 50][$i % 5] }}%x10" />
                        </div>
                    @endfor
                </div>
            @elseif ($empty)
                <atom:empty />
            @else
                <table class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @if (isset($columns) && $columns->isNotEmpty())
                        <thead data-atom-table-columns>
                            <tr {{ $columns->attributes }}>
                                {{ $columns }}
                            </tr>
                        </thead>
                    @endif

                    @if (isset($rows) && $rows->isNotEmpty())
                        <tbody {{ $rows->attributes->class(['divide-y divide-zinc-200 dark:divide-zinc-700']) }} data-atom-table-rows>
                            {{ $rows }}
                        </tbody>
                    @endif

                    @if (isset($footer) && $footer->isNotEmpty())
                        <tfoot data-atom-table-footer>
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
</div>

