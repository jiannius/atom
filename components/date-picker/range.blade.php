@props([
    'time' => false,
    'invalid' => false,
    'placeholder' => 'Select date range',
])

@php
$filterKey = $attributes->wire('model')->value();

$classes = Arr::toCssClasses([
    'h-10 w-full py-2 pl-3 pr-16 no-spinner rounded-lg shadow-xs outline-offset-1 cursor-text',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'dark:placeholder-zinc-400',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-zinc-500',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

<div
wire:ignore
x-data="dateRange({ time: @js($time) })"
x-modelable="dateRangeValue"
class="group/date-range relative"
data-atom-date-range
@if ($filterKey)
x-init="
    const emit = () => $dispatch('table-filter:set', { key: @js($filterKey), label: @js(t($placeholder)), display: dateRangeValue ? dateRangeString : null });
    $nextTick(emit);
    $watch('dateRangeValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
"
x-on:table-filter:do-clear.window="$event.detail.key === @js($filterKey) && (dateRangeValue = null, parse())"
@endif
{{ $attributes->except(['class', 'placeholder']) }}>
    <atom:dropdown x-on:open="setCalendarDates(); setCalendarRange();" locked>
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

            <div x-cloak class="z-1 absolute top-0 bottom-0 right-0 flex items-center gap-2 pr-3">
                <div x-show="dateRangeValue" x-on:click.stop="dateRangeValue = null; parse()" class="flex items-center justify-center h-full text-muted dark:text-muted-foreground hover:text-zinc-800 dark:hover:text-white">
                    <atom:icon.close />
                </div>

                {{-- A tap target of its own, so a touch user can open the calendar without
                     focusing the input (which would raise the soft keyboard). Not in the tab
                     order: keyboard users open it with ArrowDown from the input. --}}
                <button type="button" tabindex="-1" aria-label="{{ t('Open calendar') }}" class="flex items-center justify-center h-full text-muted dark:text-muted-foreground hover:text-zinc-800 dark:hover:text-white">
                    <atom:icon.calendar />
                </button>
            </div>
        </div>

        <atom:menu class="max-w-full" popover>
            <div class="w-sm md:w-[740px] overflow-auto flex divide-x divide-zinc-200 dark:divide-zinc-600">
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
                    <div class="pt-4 px-4 text-sm uppercase text-muted dark:text-muted-foreground">{{ t('Start') }}</div>

                    <atom:date-picker.calendar />

                    @if ($time)
                        <div x-show="startValue" class="px-4 pb-4">
                            <div class="text-sm text-muted dark:text-muted-foreground mb-2 uppercase">{{ t('Time') }}</div>
                            <atom:time-picker x-model="startValue" />
                        </div>
                    @endif
                </div>

                <div class="shrink-0 w-[300px]">
                    <div class="pt-4 px-4 text-sm uppercase text-muted dark:text-muted-foreground">{{ t('End') }}</div>

                    <atom:date-picker.calendar />

                    @if ($time)
                        <div x-show="endValue" class="px-4 pb-4">
                            <div class="text-sm text-muted dark:text-muted-foreground mb-2 uppercase">{{ t('Time') }}</div>
                            <atom:time-picker x-model="endValue" />
                        </div>
                    @endif
                </div>
            </div>
        </atom:menu>
    </atom:dropdown>
</div>
