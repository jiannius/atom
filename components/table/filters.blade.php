@props([
    'more' => null,   // slot: overflow filter controls
    'overflow' => null,   // 'card' => expandable card row; 'modal' => form-shaped modal; null/default => popover
    'chips' => 'below',
])

{{-- `chips="inline"` puts each active filter after the controls in the one wrapping row, as a sentence pill ("Issued is
     1 Apr 2026 to 30 Apr 2026", with a name, a muted operator and the value) that its x removes. A control can name its own
     operator in the event's `operator` detail (default `is`; the search says `:`, which reads "Search: foo").
     Pair it with `add` on a select filter or a date range, which draws the control as a dashed "+ Customer" pill that
     steps aside for its chip once a value is set. --}}
@php
$inline = $chips === 'inline';

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
    set(key, label, display, operator) {
        const empty = display === null || display === '' || (Array.isArray(display) && display.length === 0)
        if (empty) { delete this.chips[key] } else { this.chips[key] = { label, display, operator: operator ?? @js(t('is')) } }
    },
    get active() { return Object.entries(this.chips).map(([key, v]) => ({ key, label: v.label, display: v.display, operator: v.operator })) },
    clear(key) { this.$dispatch('table-filter:do-clear', { key }) },
    clearAll() { Object.keys(this.chips).forEach(k => this.clear(k)) },
}"
x-on:table-filter:set.window="set($event.detail.key, $event.detail.label, $event.detail.display, $event.detail.operator)"
{{ $attributes->class($inline ? 'flex min-h-control min-w-0 grow flex-wrap items-center gap-2' : 'grow space-y-3') }}
data-atom-table-filters
data-chips="{{ $inline ? 'inline' : 'below' }}">
    {{-- the overflow button is a filter control like the rest, so it sits in the
         same wrapping row, straight after the last one. A grow wrapper around the
         slot used to push it to the far end of the bar, where it read as a table
         action sitting with Export rather than as the last filter. --}}
    <div @class(['flex flex-wrap items-center gap-3' => ! $inline, 'contents' => $inline])>
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
                <atom:dropdown class="shrink-0" locked>
                    <atom:button variant="ghost">{{ t('More filters') }} <atom:icon.dropdown /></atom:button>
                    <atom:menu popover class="p-3 min-w-sm flex flex-wrap items-center gap-3">
                        {{ $more }}
                    </atom:menu>
                </atom:dropdown>
            @endif
        @endisset
    </div>

    @if (isset($more) && $overflow === 'card')
        <div x-show="expanded" x-cloak @class(['p-4 rounded-panel border bg-surface flex flex-wrap items-center gap-3', 'w-full' => $inline])>
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

    @if ($inline)
        <template x-for="chip in active" x-bind:key="chip.key" hidden>
            <div class="inline-flex h-[26px] items-center gap-1.5 rounded-pill bg-neutral-bg ps-2.5 pe-1.5 text-xs text-fg" data-atom-table-filter-chip>
                <span class="font-medium" x-text="chip.label"></span>
                <span class="text-fg-muted" x-text="chip.operator" x-bind:class="chip.operator === ':' && '-ms-1.5'"></span>
                <span x-text="chip.display"></span>
                <button type="button" x-on:click="clear(chip.key)" x-bind:aria-label="@js(t('Remove filter')) + ': ' + chip.label" class="flex size-4 items-center justify-center rounded-pill text-fg-muted transition-colors hover:text-fg">
                    <atom:icon.close class="size-3.5" />
                </button>
            </div>
        </template>

        <atom:button variant="ghost" size="sm" x-show="active.length" x-cloak x-on:click="clearAll()">{{ t('Clear all') }}</atom:button>
    @else
        <div x-show="active.length" x-cloak class="flex flex-wrap items-center gap-2">
            <template x-for="chip in active" x-bind:key="chip.key" hidden>
                <div class="inline-flex h-[26px] items-center gap-1.5 rounded-pill bg-neutral-bg px-2.5 text-xs text-fg" data-atom-table-filter-chip>
                    <span class="text-fg-muted" x-text="chip.label + ':'"></span>
                    <span x-text="chip.display"></span>
                    <button type="button" x-on:click="clear(chip.key)" x-bind:aria-label="@js(t('Remove filter')) + ': ' + chip.label" class="text-fg-muted transition-colors hover:text-fg">
                        <atom:icon.close class="size-3.5" />
                    </button>
                </div>
            </template>

            <atom:button variant="ghost" size="sm" x-on:click="clearAll()">{{ t('Clear all') }}</atom:button>
        </div>
    @endif
</div>
