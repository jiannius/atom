@props([
    'more' => null,   // slot: overflow filter controls
    'overflow' => null,   // 'card' => expandable card row; null/default => popover
])

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
    <div class="flex flex-wrap items-center gap-3">
        <div class="grow flex flex-wrap items-center gap-3">
            {{ $slot }}
        </div>

        @isset($more)
            <div class="shrink-0">
                @if ($overflow === 'card')
                    <atom:button variant="ghost" x-on:click="expanded = !expanded">
                        {{ t('More filters') }} <atom:icon.dropdown />
                    </atom:button>
                @else
                    <atom:dropdown>
                        <atom:button variant="ghost">{{ t('More filters') }} <atom:icon.dropdown /></atom:button>
                        <atom:menu popover class="p-3 min-w-sm flex flex-wrap items-center gap-3">
                            {{ $more }}
                        </atom:menu>
                    </atom:dropdown>
                @endif
            </div>
        @endisset
    </div>

    @if (isset($more) && $overflow === 'card')
        <div x-show="expanded" x-cloak class="p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 flex flex-wrap items-center gap-3">
            {{ $more }}
        </div>
    @endif

    <div x-show="active.length" x-cloak class="flex flex-wrap items-center gap-2">
        <template x-for="chip in active" x-bind:key="chip.key" hidden>
            <div class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-1 text-sm">
                <span class="text-muted" x-text="chip.label + ':'"></span>
                <span x-text="chip.display"></span>
                <button type="button" x-on:click="clear(chip.key)" class="text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200">
                    <atom:icon.close class="size-3.5" />
                </button>
            </div>
        </template>

        <atom:button variant="ghost" size="sm" x-on:click="clearAll()">{{ t('Clear all') }}</atom:button>
    </div>
</div>
