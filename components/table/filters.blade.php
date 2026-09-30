@props([
    'more' => null,   // slot: overflow filter controls
    'overflow' => null,   // 'card' => expandable card row; 'modal' => form-shaped modal; null/default => popover
])

@php
// The overflow modal is opened by name like any other, so it needs one that
// can't collide with a host app's own bare <atom:modal> — that one defaults to
// the enclosing Livewire component's name, so this suffixes it. current()
// returns false (not null) when no component is on the stack.
$modalName = ((app('livewire')->current() ?: null)?->getName() ?: 'atom').'-table-filters';
@endphp

<div
x-data="{
    chips: {},
    expanded: false,
    set(key, label, display) {
        const empty = display === null || display === '' || (Array.isArray(display) && display.length === 0)
        if (empty) { delete this.chips[key] } else { this.chips[key] = { label, display } }
    },
    get active() { return Object.entries(this.chips).map(([key, v]) => ({ key, label: v.label, display: v.display })) },
    clear(key) { this.$dispatch('table-filter:do-clear', { key }) },
    clearAll() { Object.keys(this.chips).forEach(k => this.clear(k)) },
}"
x-on:table-filter:set.window="set($event.detail.key, $event.detail.label, $event.detail.display)"
class="grow space-y-3"
data-atom-table-filters>
    {{-- the overflow button is a filter control like the rest, so it sits in the
         same wrapping row, straight after the last one. A grow wrapper around the
         slot used to push it to the far end of the bar, where it read as a table
         action sitting with Export rather than as the last filter. --}}
    <div class="flex flex-wrap items-center gap-3">
        {{ $slot }}

        @isset($more)
            @if ($overflow === 'card')
                <atom:button variant="ghost" class="shrink-0" x-on:click="expanded = !expanded">
                    {{ t('More filters') }} <atom:icon.dropdown />
                </atom:button>
            @elseif ($overflow === 'modal')
                <atom:modal.trigger :name="$modalName">
                    <atom:button variant="ghost" class="shrink-0">
                        {{ t('More filters') }} <atom:icon.dropdown />
                    </atom:button>
                </atom:modal.trigger>
            @else
                <atom:dropdown class="shrink-0">
                    <atom:button variant="ghost">{{ t('More filters') }} <atom:icon.dropdown /></atom:button>
                    <atom:menu popover class="p-3 min-w-sm flex flex-wrap items-center gap-3">
                        {{ $more }}
                    </atom:menu>
                </atom:dropdown>
            @endif
        @endisset
    </div>

    @if (isset($more) && $overflow === 'card')
        <div x-show="expanded" x-cloak class="p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 flex flex-wrap items-center gap-3">
            {{ $more }}
        </div>
    @endif

    @if (isset($more) && $overflow === 'modal')
        {{-- a modal has room a filter bar doesn't, so the slot is laid out as a form
             here — one field per row. The fields themselves (label + control) are the
             caller's to write: a control that isn't a filter variant still filters,
             it just registers no chip. --}}
        <atom:modal :name="$modalName" class="max-w-xl" aria-label="{{ t('More filters') }}">
            <atom:heading size="lg">{{ t('More filters') }}</atom:heading>

            <atom:form.grid cols="1" class="mt-6">
                {{ $more }}
            </atom:form.grid>
        </atom:modal>
    @endif

    <div x-show="active.length" x-cloak class="flex flex-wrap items-center gap-2">
        <template x-for="chip in active" x-bind:key="chip.key" hidden>
            <div class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-1 text-sm" data-atom-table-filter-chip>
                <span class="text-zinc-600 dark:text-zinc-300" x-text="chip.label + ':'"></span>
                <span x-text="chip.display"></span>
                <button type="button" x-on:click="clear(chip.key)" class="text-zinc-600 dark:text-zinc-300 hover:text-zinc-800 dark:hover:text-zinc-200">
                    <atom:icon.close class="size-3.5" />
                </button>
            </div>
        </template>

        <atom:button variant="ghost" size="sm" x-on:click="clearAll()">{{ t('Clear all') }}</atom:button>
    </div>
</div>
