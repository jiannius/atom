@props([
    'align' => 'left',
    'sort' => null,
    'checkbox' => false,
])

@php
$classes = Arr::toCssClasses([
    'py-1.5 px-3 inline-flex items-center gap-2',
    'whitespace-nowrap uppercase text-sm text-zinc-500 font-medium',
    'leading-6 tracking-wider',

    match ($align) {
        'left' => 'justify-start',
        'center' => 'justify-center',
        'right' => 'justify-end',
    },

    $sort ? 'cursor-pointer text-zinc-900 dark:text-white underline decoration-dotted underline-offset-5' : '',
]);
@endphp

<th @class([
    'p-1 bg-zinc-100 dark:bg-transparent border-b border-zinc-200 dark:border-zinc-700 sticky top-0 z-1',
    match ($align) {
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    },    
])>
    <div
    @if ($sort)
        x-data="{ sort: @js($sort) }"
        x-on:click="() => {
            if ($wire._table.sort.column !== sort) {
                $wire.set('_table.sort.column', sort)
                $wire.set('_table.sort.direction', 'asc')
            }
            else if ($wire._table.sort.direction === 'asc') {
                $wire.set('_table.sort.direction', 'desc')
            }
            else {
                $wire.set('_table.sort.column', null)
                $wire.set('_table.sort.direction', null)
            }
        }"
    @endif
    {{ $attributes->class($classes) }}>
        <div class="grow">
            @if ($checkbox)
                <atom:table.checkbox
                x-data="{
                    isToggled: false,

                    get checkboxes () {
                        return this.$el.closest('table').querySelectorAll('tbody [data-atom-table-checkbox]')
                    },

                    init () {
                        this.$watch('isToggled', () => this.toggle())
                    },

                    toggle () {
                        this.checkboxes.forEach(checkbox => {
                            if (
                                (this.isToggled && !checkbox.getAttribute('data-checked'))
                                || (!this.isToggled && checkbox.getAttribute('data-checked'))
                            ) {
                                checkbox.click()
                            }
                        })
                    },
                }"
                x-on:click="isToggled = !isToggled"
                x-bind:data-checked="$wire._table.checkboxes.length > 0 && $wire._table.checkboxes.length === checkboxes.length" />
            @else
                {{ $slot }}
            @endif
        </div>

        @if ($sort)
            <div x-show="$wire._table.sort.column" class="shrink-0 flex items-center justify-center text-zinc-500 size-3">
                <atom:icon.arrow-down x-show="$wire._table.sort.column === '{{ $sort }}' && $wire._table.sort.direction === 'asc'"/>
                <atom:icon.arrow-up x-show="$wire._table.sort.column === '{{ $sort }}' && $wire._table.sort.direction === 'desc'"/>
            </div>
        @endif
    </div>
</th>
