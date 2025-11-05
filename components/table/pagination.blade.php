@props([
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
])

<nav
role="navigation"
aria-label="{{ t('atom::pagination.navigation') }}"
x-on:paginate="document.querySelector('body').scrollIntoView()"
class="py-2 px-4 flex flex-wrap items-center justify-between gap-3">
    <div class="py-2 text-sm text-muted">
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
                        <span class="relative inline-flex items-center size-8 text-sm font-medium text-zinc-300 dark:text-zinc-600 cursor-default rounded-md leading-5" aria-hidden="true">
                            <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" dusk="previousPage{{ $paginate->getPageName() == 'page' ? '' : '.' . $paginate->getPageName() }}.after" class="relative inline-flex items-center size-8 font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-gray-300" aria-label="{{ t('pagination.previous') }}">
                        <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif

                {{-- Pagination Elements --}}
                @foreach (data_get($paginate->links()->getData(), 'elements', []) as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-default leading-5 dark:bg-gray-800 dark:border-gray-600">{{ $element }}</span>
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginate->currentPage())
                                <span aria-current="page" class="relative inline-flex items-center size-8 text-sm font-medium cursor-default rounded-md leading-5 bg-zinc-200 dark:text-white dark:bg-zinc-800">
                                    <span class="m-auto">{{ $page }}</span>
                                </span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" class="relative inline-flex items-center size-8 text-sm font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-zinc-300" aria-label="{{ t('Go to page :page', ['page' => $page]) }}">
                                    <span class="m-auto">{{ $page }}</span>
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginate->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginate->getPageName() }}')" x-on:click="$dispatch('paginate')" dusk="nextPage{{ $paginate->getPageName() == 'page' ? '' : '.' . $paginate->getPageName() }}.after" class="relative inline-flex items-center size-8 font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-gray-300" aria-label="{{ t('pagination.next') }}">
                        <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <span aria-disabled="true" aria-label="{{ t('atom::pagination.next') }}">
                        <span class="relative inline-flex items-center size-8 text-sm font-medium text-zinc-300 dark:text-zinc-600 cursor-default rounded-md leading-5" aria-hidden="true">
                            <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    @endif
</nav>
