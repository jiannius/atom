@props([
    'paginate' => null,
    'search' => true,
    'trashed' => null,
    'total' => null,
    'maxRows' => [50, 100, 200, 400],
])

@php
$total ??= $paginate?->total();
$filtered = isset($this->filters)
    ? collect($this->filters)->except('search')->filter()->values()->count() > 0
    : false;
@endphp

<div {{ $attributes->class('group/table rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm divide-y dark:divide-zinc-700') }} data-atom-table>
    @if (isset($header))
        <div class="relative py-3 px-4" data-atom-table-header>
            {{ $header }}
        </div>
    @elseif ($paginate || $search || isset($filters))
        <div class="relative py-3 px-4 flex flex-wrap justify-between items-center gap-2" data-atom-table-header>
            <div class="shrink-0 text-gray-800 flex items-center gap-3">
                @if (is_numeric($total))
                    <div class="font-medium leading-snug dark:text-white">
                        {{ t('atom::messages.table-rows-count', $total) }}
                    </div>

                    @if ($maxRows)
                        <atom:dropdown>
                            <button type="button" class="text-sm text-muted flex items-center gap-1">
                                <span x-text="$wire._table.max_rows"></span><span>/ {{ t('atom::messages.table-page') }}</span>
                                <atom:icon.dropdown />
                            </button>

                            <atom:menu popover>
                                @foreach ($maxRows as $maxRow)
                                    <atom:menu.item wire:click="$set('_table.max_rows', {{ $maxRow }})">
                                        {{ $maxRow }} / {{ t('atom::messages.table-page') }}
                                    </atom:menu.item>
                                @endforeach
                            </atom:menu>
                        </atom:dropdown>
                    @endif
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($search)
                    <div x-data="{ text: '' }" class="flex items-center justify-center gap-2">
                        <atom:icon.search class="shrink-0 size-5"/>

                        <input
                        type="text"
                        x-model="text"
                        @if (isset($this->filters))
                        x-on:keydown.enter.prevent="$wire.set('filters.search', text)"
                        @else
                        x-on:keydown.enter.prevent="$dispatch('search', text)"
                        @endif
                        placeholder="{{ t('atom::messages.search') }}"
                        x-bind:class="text ? 'w-40' : 'w-14'"
                        class="focus:outline-none focus:w-40 transition-all duration-100">

                        <button
                        type="button"
                        x-show="text"
                        @if (isset($this->filters))
                        x-on:click="$wire.set('filters.search', null); text = ''"
                        @else
                        x-on:click="$dispatch('search', null); text = ''"
                        @endif
                        class="shrink-0 text-zinc-400 dark:text-white cursor-pointer size-5 flex items-center justify-center">
                            <atom:icon.close />
                        </button>
                    </div>
                @endif

                @if ($trashed || isset($filters))
                    <div class="flex items-center gap-1 flex-wrap">
                        @isset ($filters)
                            <div {{ $filters->attributes->merge(['wire:ignore' => true]) }}>
                                <atom:dropdown locked>
                                    <atom:tooltip content="atom::messages.filters">
                                        <button type="button" class="relative flex items-center justify-center rounded-md p-1 mx-1 hover:bg-zinc-200 dark:hover:bg-zinc-700">
                                            <atom:icon.filter class="size-5"/>

                                            @if ($filtered)
                                                <span class="size-2.5 rounded-full bg-red-500 absolute top-0 right-0"></span>
                                            @endif
                                        </button>
                                    </atom:tooltip>

                                    <atom:menu class="min-w-sm max-w-lg" popover>
                                        <div class="p-3">
                                            <div class="text-xs text-muted-foreground uppercase font-medium mb-3">
                                                {{ t('atom::messages.filters') }}
                                            </div>

                                            <div class="space-y-6">
                                                {{ $filters }}
                                            </div>
                                        </div>
                                    </atom:menu>
                                </atom:dropdown>
                            </div>
                        @endisset

                        <div @class([
                            'flex items-center gap-1',
                            '[&_button]:relative [&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:gap-2',
                            '[&_button]:rounded [&_button]:p-1 [&_button]:mx-1',
                            '[&_button]:text-sm [&_button]:hover:bg-zinc-200 dark:[&_button]:hover:bg-zinc-700',
                            '[&_button_[data-atom-icon]]:size-5',
                        ])>
                            @isset ($actions)
                                {{ $actions }}
                            @endisset

                            @if ($trashed)
                                <atom:tooltip content="atom::messages.show-trashed">
                                    <button type="button" wire:click="$set('_table.show_trashed', true)">
                                        <atom:icon.delete />
                                        <div class="size-2.5 rounded-full bg-red-500 absolute top-0 right-0"></div>
                                    </button>
                                </atom:tooltip>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if (isset($checked) && $checked->isNotEmpty())
        <template x-if="$wire._table.checkboxes.length" hidden>
            <div class="py-3 px-4 flex items-center gap-3" data-atom-table-checked>
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-400">
                    <atom:icon.double-check class="size-5"/>
                    <div>
                        <span x-text="$wire._table.checkboxes.length"></span> {{ t('atom::messages.selected') }}
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    {{ $checked }}
                </div>
            </div>
        </template>
    @endif

    @if ($trashed)
        <template x-if="$wire._table.show_trashed && !$wire._table.checkboxes.length" hidden>
            <div class="py-3 px-4 text-zinc-400 font-medium flex items-center gap-3">
                <atom:link icon="left" wire:click="$set('_table.show_trashed', false)" />

                <div class="flex items-center gap-2 font-medium">
                    <atom:icon.delete class="size-5" />
                    {{ t('atom::messages.showing-trashed', $total) }}
                </div>

                <atom:confirm.trigger
                variant="danger"
                heading="atom::messages.clear-all-trashed"
                message="atom::messages.this-will-permanently-delete-all-selected-records"
                x-on:confirmed="$wire.emptyTrashed()">
                    <atom:link icon="delete">{{ t('atom::messages.empty-trashed') }}</atom:link>
                </atom:confirm.trigger>
            </div>
        </template>
    @endif

    <div
    x-bind:class="$wire._table.checkboxes.length && 'rounded-t-none'"
    class="overflow-hidden last:rounded-b-lg rounded-t-lg group-has-[[data-atom-table-header]]/table:rounded-t-none">
        <div class="overflow-x-auto">
            @if ($paginate && !$total)
                <atom:empty/>
            @else
                <table class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-150 dark:divide-zinc-700">
                    @if (isset($columns) && $columns->isNotEmpty())
                        <thead data-atom-table-columns>
                            <tr {{ $columns->attributes }}>
                                {{ $columns }}
                            </tr>
                        </thead>
                    @endif

                    @if (isset($rows) && $rows->isNotEmpty())
                        <tbody {{ $rows->attributes->class(['divide-y divide-zinc-150 dark:divide-zinc-700']) }} data-atom-table-rows>
                            {{ $rows }}
                        </tbody>
                    @endif
                </table>

                @isset ($footer)
                    <div class="border-t border-zinc-200 p-3">
                        {{ $footer }}
                    </div>
                @endisset
            @endif
        </div>
    </div>

    {{ $paginate->links('atom::pagination') }}
</div>
