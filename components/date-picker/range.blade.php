@props([
    'time' => false,
    'invalid' => false,
    'placeholder' => 'Select date range',
    'add' => false,
])

@php
$filterKey = $attributes->wire('model')->value() ?: $attributes->get('data-filter-key');

/* The field recipe (see input/general); two 16px icons (clear, calendar) sit at the end. */
$classes = Arr::toCssClasses([
    'h-control w-full pl-2.5 pr-14 no-spinner rounded-control border bg-surface text-sm text-fg cursor-text',
    'placeholder:text-fg-subtle',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);

/* The add-filter pill: the same button as the select filter's, a 26px dashed pill that reads "+ Label" until a range is set. It
   is a button (there is nothing to type in it), so the range is set from the calendar. In an `<atom:table.filters chips="inline">`
   bar it is also the chip once a range is set ("Issued is 1 Apr 2026 to 30 Apr 2026", with an x beside it) and opens the same
   calendar; see select/filter.blade.php, which draws it the same way (date-range.js `selfChip`). */
$pillClasses = Arr::toCssClasses([
    'inline-flex h-[26px] items-center gap-1 appearance-none rounded-pill border border-dashed border-control-border bg-transparent px-2.5 text-xs text-fg-muted',
    'hover:bg-surface-sunken hover:text-fg',
    '[&[data-chip]]:gap-1.5 [&[data-chip]]:rounded-e-none [&[data-chip]]:border-transparent [&[data-chip]]:bg-neutral-bg [&[data-chip]]:pe-2 [&[data-chip]]:text-fg [&[data-chip]]:hover:bg-border-strong',
]);
@endphp

<div
wire:ignore
x-data="dateRange({ time: @js($time), add: @js((bool) $add) })"
x-modelable="dateRangeValue"
class="group/date-range relative"
@if ($add)
x-show="selfChip || !dateRangeValue"
x-bind:data-atom-filter-active="chipState ? '' : null"
@endif
data-atom-date-range
@if ($filterKey) data-atom-filter-key="{{ $filterKey }}" @endif
@if ($filterKey)
x-init="
    const emit = () => $dispatch('table-filter:set', { key: @js($filterKey), label: @js(t($placeholder)), display: dateRangeValue ? dateRangeString : null, operator: @js(t('is')), self: selfChip });
    $nextTick(emit);
    $watch('dateRangeValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
"
x-on:table-filter:do-clear.window="$event.detail.key === @js($filterKey) && (dateRangeValue = null, parse())"
@endif
{{ $attributes->except(['class', 'placeholder']) }}>
    <atom:dropdown x-on:open="setCalendarDates(); setCalendarRange();" locked :class="$add ? 'flex items-center' : ''" x-bind:data-atom-table-filter-chip="chipState ? '' : null">
        @if ($add)
        <button
        type="button"
        aria-haspopup="dialog"
        x-bind:aria-label="chipState ? @js(t($placeholder).' '.t('is')) + ' ' + dateRangeString : null"
        x-bind:data-chip="chipState ? '' : null"
        {{ $attributes->class($pillClasses)->only('class') }}
        data-atom-dropdown-trigger
        data-atom-date-range-add
        data-atom-filter-chip-body>
            <atom:icon.add class="size-3.5" x-show="!chipState" />
            <span x-bind:class="chipState && 'font-medium'">{{ t($placeholder) }}</span>
            <span x-show="chipState" x-cloak class="text-fg-muted">{{ t('is') }}</span>
            <span x-show="chipState" x-cloak x-text="dateRangeString" class="max-w-72 truncate"></span>
            <atom:icon.chevron-down x-show="chipState" x-cloak class="-ms-0.5 size-3 text-fg-muted" />
        </button>

        {{-- the own chip's x, split from the chip by a hairline (see select/filter.blade.php) --}}
        <button
        type="button"
        x-show="chipState"
        x-cloak
        x-on:click="clearChip()"
        x-bind:aria-label="@js(t('Remove filter')) + ': ' + @js(t($placeholder))"
        class="inline-flex h-[26px] w-6 shrink-0 items-center justify-center rounded-e-pill border-s border-border-strong bg-neutral-bg text-fg-muted transition-colors hover:bg-border-strong hover:text-fg"
        data-atom-filter-chip-clear>
            <atom:icon.close class="size-3.5" />
        </button>
        @else
        {{-- A div, not a button: the input is typed into, and a button may not hold one. --}}
        <div class="relative w-full" data-atom-dropdown-trigger>
            <input
            type="text"
            x-bind:value="dateRangeString"
            x-on:blur="commitTyped($el)"
            x-on:keydown.enter="typedEnter($event)"
            x-on:keydown.escape="revertTyped($el)"
            x-on:keydown.down.prevent="show()"
            autocomplete="off"
            spellcheck="false"
            {{ $attributes->class($classes)->merge(['placeholder' => t($placeholder)])->only(['class', 'placeholder']) }}>

            <div x-cloak class="z-1 absolute top-0 bottom-0 right-0 flex items-center gap-2 pr-2.5 text-fg-subtle">
                <div x-show="dateRangeValue" x-on:click.stop="dateRangeValue = null; parse()" class="flex items-center justify-center h-full hover:text-fg">
                    <atom:icon.close class="size-4" />
                </div>

                {{-- A tap target of its own, so a touch user can open the calendar without
                     focusing the input (which would raise the soft keyboard). Not in the tab
                     order: keyboard users open it with ArrowDown from the input. --}}
                <button type="button" tabindex="-1" aria-label="{{ t('Open calendar') }}" class="flex items-center justify-center h-full hover:text-fg">
                    <atom:icon.calendar class="size-4" />
                </button>
            </div>
        </div>
        @endif

        <atom:menu class="max-w-full" popover>
            <div class="w-sm md:w-[740px] overflow-auto flex divide-x divide-border">
                <div class="shrink-0 w-40">
                    <atom:menu.item x-on:click.stop="preset('today')">{{ t('Today') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('yesterday')">{{ t('Yesterday') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('last-7-days')">{{ t('Last 7 Days') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('last-30-days')">{{ t('Last 30 Days') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('last-180-days')">{{ t('Last 180 Days') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('this-month')">{{ t('This Month') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('last-month')">{{ t('Last Month') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('this-year')">{{ t('This Year') }}</atom:menu.item>
                    <atom:menu.item x-on:click.stop="preset('last-year')">{{ t('Last Year') }}</atom:menu.item>
                </div>

                <div class="shrink-0 w-[300px]">
                    <div class="pt-3 px-3 text-xs font-medium text-fg-muted">{{ t('Start') }}</div>

                    <atom:date-picker.calendar />

                    @if ($time)
                        <div x-show="startValue" class="px-4 pb-4">
                            <div class="text-xs font-medium text-fg-muted mb-2">{{ t('Time') }}</div>
                            <atom:time-picker x-model="startValue" />
                        </div>
                    @endif
                </div>

                <div class="shrink-0 w-[300px]">
                    <div class="pt-3 px-3 text-xs font-medium text-fg-muted">{{ t('End') }}</div>

                    <atom:date-picker.calendar />

                    @if ($time)
                        <div x-show="endValue" class="px-4 pb-4">
                            <div class="text-xs font-medium text-fg-muted mb-2">{{ t('Time') }}</div>
                            <atom:time-picker x-model="endValue" />
                        </div>
                    @endif
                </div>
            </div>
        </atom:menu>
    </atom:dropdown>
</div>
