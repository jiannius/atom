@props([
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'sticky' => true,
])

@php
app('atom')->requireTrait(\Jiannius\Atom\Traits\WithTable::class, '<atom:table.pagination>');
app('atom')->requireTrait(\Livewire\WithPagination::class, '<atom:table.pagination>');

/* One recipe for every cell of the pager: a control-high square (28px, 40 on touch), a 7px radius, tabular figures. A disabled
   end is dimmed, the current page sits on the neutral fill with a strong hairline, a link tints on hover. The focus ring is the
   base :focus-visible outline (the gap between cells leaves room for it). */
$cell = 'inline-flex h-control min-w-(--atom-control-h) shrink-0 items-center justify-center rounded-control px-1.5 text-sm font-medium leading-5 tabular-nums';
$cellLink = $cell.' text-fg-muted transition-colors hover:bg-fg/[0.05] hover:text-fg';
$cellOff = $cell.' cursor-default text-fg-subtle opacity-50';
$cellCurrent = $cell.' cursor-default border border-border-strong bg-neutral-bg text-fg';
@endphp

<nav
{{ $attributes->merge([
    'role' => 'navigation',
    'aria-label' => t('atom::pagination.navigation'),
    'x-on:paginate' => "document.querySelector('body').scrollIntoView()",
    'data-atom-table-pager' => true,
])->class([
    'py-2 px-4 flex flex-wrap items-center justify-between gap-3 tabular-nums',
    // pinned to the bottom of whatever scrolls (the page, a modal) while the table runs past it, in place at its end: a raised
    // surface with a hairline of its own above it (the box's divide-y draws its hairline under the rows above, which scrolls away),
    // pulled up a pixel over that one so at rest it is still one line. A page that has a save bar (also pinned to the bottom, and
    // the screen's one primary action) keeps it in flow: two pinned bars at one edge would cover each other. So does a phone, where
    // 135px of an 800px screen is too much to give a pager (it shows the range itself, and the toolbar's count is hidden there).
    // Pinned, it clears the home indicator (the safe-area inset) and is a mark the toast lift looks for (helpers/toast-lift.js).
    'sticky bottom-0 z-10 -mt-px border-t border-border bg-surface/95 backdrop-blur-sm sm:pb-[max(0.5rem,env(safe-area-inset-bottom,0px))] max-sm:static [body:has([data-atom-save-bar])_&]:static' => $sticky,
]) }}>
    <div class="py-2 text-xs text-fg-muted">
        @if ($paginate->firstItem())
            {!! t('atom::pagination.showing-from-rows-to-rows', [
                'from' => $paginate->firstItem(),
                'to' => $paginate->lastItem(),
                'total' => $paginate->total(),
            ]) !!}
        @else
            {!! t('atom::pagination.showing-rows-count', [
                'count' => $paginate->count(),
                'total' => $paginate->total(),
            ]) !!}
        @endif
    </div>
    
    @if ($paginate->hasPages())
        @if ($maxRows)
            <atom:dropdown>
                <atom:button variant="ghost" size="sm">
                    <span x-text="$wire._table.max_rows"></span> <span>{{ t('atom::pagination.rows') }}</span> <span>/ {{ t('atom::pagination.page') }}</span> <atom:icon.dropdown />
                </atom:button>

                <atom:menu popover>
                    @foreach ($maxRows as $maxRow)
                        <atom:menu.item wire:click="$set('_table.max_rows', {{ $maxRow }})">
                            {{ $maxRow }} {{ t('rows') }} / {{ t('atom::pagination.page') }}
                        </atom:menu.item>
                    @endforeach
                </atom:menu>
            </atom:dropdown>
        @endif

        <div>
            <span class="relative z-0 inline-flex gap-1 rtl:flex-row-reverse">
                {{-- Previous Page Link --}}
                @if ($paginate->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ t('atom::pagination.previous') }}">
                        <span class="{{ $cellOff }}" aria-hidden="true">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" dusk="previousPage{{ $paginate->getPageName() == 'page' ? '' : '.' . $paginate->getPageName() }}.after" class="{{ $cellLink }}" aria-label="{{ t('pagination.previous') }}">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif

                {{-- Pagination Elements --}}
                @foreach (data_get($paginate->links()->getData(), 'elements', []) as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <atom:dropdown locked>
                            <button type="button" class="{{ $cell }} cursor-pointer text-fg-muted transition-colors hover:text-fg" aria-label="{{ t('Has more pages') }}">
                                {{ $element }}
                            </button>

                            <atom:menu popover>
                                <div
                                x-data="{ page: null }"
                                x-intersect="$root.querySelector('input').focus()"
                                class="flex items-center gap-2 px-3 text-sm">
                                    <div class="shrink-0">{{ t('Go to page') }}</div>
                                    <div class="grow">
                                        <input
                                        type="number"
                                        x-model="page"
                                        x-on:keydown.enter.stop.prevent="$wire.gotoPage(page, '{{ $paginate->getPageName() }}'); $root.parentNode.hidePopover()"
                                        class="h-control-sm w-20 rounded-control border border-control-border bg-surface px-2 text-sm tabular-nums"
                                        placeholder="1" />
                                    </div>
                                </div>
                            </atom:menu>
                        </atom:dropdown>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginate->currentPage())
                                <span aria-current="page" class="{{ $cellCurrent }}">
                                    <span>{{ $page }}</span>
                                </span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" class="{{ $cellLink }}" aria-label="{{ t('Go to page :page', ['page' => $page]) }}">
                                    <span>{{ $page }}</span>
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginate->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" dusk="nextPage{{ $paginate->getPageName() == 'page' ? '' : '.' . $paginate->getPageName() }}.after" class="{{ $cellLink }}" aria-label="{{ t('pagination.next') }}">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <span aria-disabled="true" aria-label="{{ t('atom::pagination.next') }}">
                        <span class="{{ $cellOff }}" aria-hidden="true">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    @endif
</nav>
