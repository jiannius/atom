@props([
    'paginator' => null,
    'simple' => false,
    'summary' => true,
])

<nav
role="navigation"
aria-label="{{ t('atom::pagination.navigation') }}"
x-on:paginate="document.querySelector('body').scrollIntoView()"
class="py-2 px-4 flex flex-wrap items-center justify-between gap-3">
    @if ($summary && method_exists($paginator, 'total'))
        <div class="py-2 text-sm text-muted">
            @if ($paginator->firstItem())
                {!! t('atom::pagination.showing-from-rows-to-rows', [
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ]) !!}
            @else
                {!! t('atom::pagination.showing-rows-count', [
                    'count' => $paginator->count(),
                    'total' => $paginator->total(),
                ]) !!}
            @endif
        </div>
    @endif

    @if ($paginator->hasPages())
        <div>
            <span class="relative z-0 inline-flex gap-1 rtl:flex-row-reverse">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ t('atom::pagination.previous') }}">
                        <span class="relative inline-flex items-center size-8 text-sm font-medium text-zinc-300 dark:text-zinc-600 cursor-default rounded-md leading-5" aria-hidden="true">
                            <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="$dispatch('paginate')" class="relative inline-flex items-center size-8 font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-gray-300" aria-label="{{ t('atom::pagination.previous') }}">
                        <svg class="size-5 m-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif

                @unless ($simple)
                    {{-- Pagination Elements --}}
                    @foreach (data_get($paginator->links()->getData(), 'elements', []) as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <atom:dropdown locked>
                                <button type="button" class="relative inline-flex items-center px-2 text-sm font-medium text-gray-700 bg-white cursor-pointer leading-5 dark:text-zinc-400 dark:bg-zinc-800" aria-label="{{ t('Has more pages') }}">
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
                                            x-on:keydown.enter.stop.prevent="$wire.gotoPage(page, '{{ $paginator->getPageName() }}'); $root.parentNode.hidePopover()"
                                            class="w-20 px-2 py-1 text-sm"
                                            placeholder="1" />
                                        </div>
                                    </div>
                                </atom:menu>
                            </atom:dropdown>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="relative inline-flex items-center size-8 text-sm font-medium cursor-default rounded-md leading-5 underline underline-offset-4 decoration-dotted bg-zinc-200 dark:text-white dark:bg-zinc-800">
                                        <span class="m-auto">{{ $page }}</span>
                                    </span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="$dispatch('paginate')" class="relative inline-flex items-center size-8 text-sm font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-zinc-300" aria-label="{{ t('Go to page :page', ['page' => $page]) }}">
                                        <span class="m-auto">{{ $page }}</span>
                                    </button>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                @endunless

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="$dispatch('paginate')" class="relative inline-flex items-center size-8 font-medium text-zinc-400 rounded-md leading-5 hover:bg-zinc-100 dark:hover:bg-zinc-800 focus:z-10 focus:outline-none focus:ring ring-gray-300" aria-label="{{ t('atom::pagination.next') }}">
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
