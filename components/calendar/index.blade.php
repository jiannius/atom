@props([
    'items' => [],
    'views' => ['month'],
    'view' => null,
    'weekStart' => 'monday',
    'weekend' => ['saturday', 'sunday'],
    'max' => 3,
    'timeFormat' => 'g:i A',
    'dateFormat' => 'j M Y',
    'selectable' => false,
    'draggable' => true,
    'resizable' => true,
    'slotDuration' => 30,
    'scrollTime' => '06:00',
    'nowIndicator' => true,
    'month' => null,
])

@php
// The bound property is the anchor date. With none the calendar is static: it shows `month` (or the
// current one) and its views can be switched, but there is nowhere to navigate to.
$model = $attributes->wire('model')->value();
$component = app('livewire')->current() ?: null;
$anchor = $model && $component ? data_get($component, $model) : $month;

$calendar = \Jiannius\Atom\Services\Calendar::make([
    'views' => (array) $views,
    'view' => $view,
    'week_start' => $weekStart,
    'weekend' => $weekend,
    // an explicit null falls back to the default 3 in @props, so false asks for no limit
    'max' => $max === false ? null : $max,
    'time_format' => $timeFormat,
    'date_format' => $dateFormat,
    'selectable' => $selectable,
    'draggable' => $draggable,
    'resizable' => $resizable,
    'slot_duration' => $slotDuration,
    'scroll_time' => $scrollTime,
    'now_indicator' => $nowIndicator,
]);

$payload = $calendar->payload($anchor, $items ?? []);

// A colour is decoration: tint behind a chip, an accent edge and a dot, each readable against its
// ground in light and dark. Chip text stays neutral. The strings are written out here, not built in
// JS, so the host's Tailwind finds them. A host adds or replaces a colour in config('atom.calendar.colors')
// with the same three keys, and has to make its own build see those classes.
$palette = [
    'red' => ['tint' => 'bg-red-100 dark:bg-red-500/20', 'accent' => 'border-red-600 dark:border-red-400', 'dot' => 'bg-red-600 dark:bg-red-400'],
    'orange' => ['tint' => 'bg-orange-100 dark:bg-orange-500/20', 'accent' => 'border-orange-600 dark:border-orange-400', 'dot' => 'bg-orange-600 dark:bg-orange-400'],
    'yellow' => ['tint' => 'bg-yellow-100 dark:bg-yellow-500/20', 'accent' => 'border-yellow-600 dark:border-yellow-400', 'dot' => 'bg-yellow-600 dark:bg-yellow-400'],
    'green' => ['tint' => 'bg-green-100 dark:bg-green-500/20', 'accent' => 'border-green-600 dark:border-green-400', 'dot' => 'bg-green-600 dark:bg-green-400'],
    'blue' => ['tint' => 'bg-sky-100 dark:bg-sky-500/20', 'accent' => 'border-sky-600 dark:border-sky-400', 'dot' => 'bg-sky-600 dark:bg-sky-400'],
    'purple' => ['tint' => 'bg-purple-100 dark:bg-purple-500/20', 'accent' => 'border-purple-600 dark:border-purple-400', 'dot' => 'bg-purple-600 dark:bg-purple-400'],
    'black' => ['tint' => 'bg-zinc-200 dark:bg-zinc-100/15', 'accent' => 'border-zinc-900 dark:border-zinc-100', 'dot' => 'bg-zinc-900 dark:bg-zinc-100'],
    'zinc' => ['tint' => 'bg-zinc-100 dark:bg-zinc-500/20', 'accent' => 'border-zinc-500 dark:border-zinc-400', 'dot' => 'bg-zinc-500 dark:bg-zinc-400'],
    'gray' => ['tint' => 'bg-zinc-100 dark:bg-zinc-500/20', 'accent' => 'border-zinc-500 dark:border-zinc-400', 'dot' => 'bg-zinc-500 dark:bg-zinc-400'],
];

foreach ((array) config('atom.calendar.colors', []) as $name => $classes) {
    if (is_string($name) && is_array($classes)) {
        $palette[$name] = collect($classes)->only(['tint', 'accent', 'dot'])->filter(fn ($class) => is_string($class))->all() + ($palette[$name] ?? []);
    }
}

$icons = collect($payload['items'])->pluck('icon')->push('warning')->filter()->unique()->values();
@endphp

<div
x-data="calendar"
@if ($model) data-atom-calendar-model="{{ $model }}" @endif
data-atom-calendar-palette="{{ json_encode($palette, JSON_THROW_ON_ERROR) }}"
data-atom-calendar-payload="{{ json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
data-atom-calendar
{{ $attributes->whereDoesntStartWith('wire:model')->class([
    'relative min-w-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs',
]) }}>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-700 p-3">
        {{-- Everything the engine drives is wire:ignore: Livewire re-renders around it and never into it. --}}
        <div wire:ignore class="flex min-w-0 items-center gap-3">
            @if ($model)
                <atom:button.group>
                    <atom:button size="sm" icon="left" :aria-label="t('Previous')" x-on:click="prev()" />
                    <atom:button size="sm" x-on:click="today()">{{ t('Today') }}</atom:button>
                    <atom:button size="sm" icon="right" :aria-label="t('Next')" x-on:click="next()" />
                </atom:button.group>

                <atom:date-picker x-model="jump">
                    <button type="button" x-bind:aria-label="labels.ui?.choose_date" class="flex items-center gap-1.5 rounded-sm text-base @2xl:text-lg outline-offset-2 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
                        <span x-text="title.month" class="font-semibold text-zinc-900 dark:text-white"></span>
                        <span x-text="title.year" class="font-normal text-zinc-500 dark:text-zinc-400"></span>
                        <atom:icon.dropdown class="size-4 text-zinc-500 dark:text-zinc-400" />
                    </button>
                </atom:date-picker>
            @else
                <div class="flex items-center gap-1.5 text-base @2xl:text-lg">
                    <span x-text="title.month" class="font-semibold text-zinc-900 dark:text-white"></span>
                    <span x-text="title.year" class="font-normal text-zinc-500 dark:text-zinc-400"></span>
                </div>
            @endif

            <span x-show="loading" class="inline-flex text-zinc-500 dark:text-zinc-400"><atom:icon.loading class="size-4" /></span>

            <div x-text="title.text" role="status" aria-live="polite" data-atom-calendar-sr></div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @endif

            <div wire:ignore x-show="views.length > 1" role="group" class="inline-flex items-center rounded-md bg-zinc-100 p-1 dark:bg-zinc-700">
                <template x-for="name in views" :key="name">
                    <button
                    type="button"
                    x-on:click="setView(name)"
                    x-bind:aria-pressed="view === name ? 'true' : 'false'"
                    x-bind:class="view === name ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 dark:text-zinc-300'"
                    class="h-6 rounded-sm px-3 text-sm font-medium outline-offset-1 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"
                    x-text="labels.ui?.[name]"></button>
                </template>
            </div>
        </div>
    </div>

    <div wire:ignore data-atom-calendar-body>
        <div x-show="view === 'month'" role="grid">
            <div role="row" data-atom-calendar-weekdays class="border-b border-zinc-200 dark:border-zinc-700">
                <template x-for="day in labels.weekdays" :key="day.key">
                    <div role="columnheader" x-text="day.short" x-bind:aria-label="day.long" class="py-2 text-center text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></div>
                </template>
            </div>

            <div data-atom-calendar-weeks>
                <template x-for="week in weeks" :key="week.key">
                    <div data-atom-calendar-week>
                        <div role="row" data-atom-calendar-week-bg>
                            <template x-for="day in week.days" :key="day.date">
                                <div
                                role="gridcell"
                                x-bind:data-date="day.date"
                                x-bind:aria-label="dayLabel(day)"
                                x-bind:aria-current="day.today ? 'date' : null"
                                x-bind:class="{
                                    'bg-zinc-50 dark:bg-zinc-900/40': day.outside,
                                    'bg-zinc-50 dark:bg-zinc-900/30': day.weekend && !day.outside,
                                    'cursor-pointer': day.count > 0 && !config.selectable,
                                }"
                                x-on:click="dayClick(day, $event)"
                                data-atom-calendar-day
                                class="border-t border-zinc-200 dark:border-zinc-700 not-first:border-l"></div>
                            </template>
                        </div>

                        <div data-atom-calendar-chips>
                            <template x-for="day in week.days" :key="'number-' + day.date">
                                <div x-bind:style="{ gridColumn: String(day.col), gridRow: '1' }" data-atom-calendar-number class="flex items-center">
                                    <span
                                    x-text="day.number"
                                    x-bind:class="day.today ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (day.outside || day.past ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-900 dark:text-zinc-100')"
                                    class="inline-flex size-6 items-center justify-center rounded-full text-sm font-medium tabular-nums"></span>
                                </div>
                            </template>

                            <template x-for="chip in week.chips" :key="chip.key">
                                <div x-bind:style="slotStyle(chip)" data-atom-calendar-slot>
                                    <atom:calendar.chip />
                                </div>
                            </template>

                            <template x-for="more in week.more" :key="more.key">
                                <button
                                type="button"
                                x-bind:style="slotStyle(more)"
                                x-on:click="openDay(more.date, $event.currentTarget)"
                                x-text="moreLabel(more.count)"
                                data-atom-calendar-more
                                class="h-5 w-full rounded-sm px-1.5 text-left text-xs font-medium text-zinc-600 outline-offset-1 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700/60 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"></button>
                            </template>

                            <template x-for="day in week.days" :key="'dots-' + day.date">
                                <div x-show="day.dots.length" x-bind:style="{ gridColumn: String(day.col), gridRow: '2' }" data-atom-calendar-dots>
                                    <template x-for="dot in day.dots" :key="dot.id">
                                        <span
                                        x-bind:class="dot.danger ? 'bg-red-600 dark:bg-red-400' : colorClass(dot, 'dot')"
                                        x-bind:data-atom-calendar-hex="isHex(dot) && !dot.danger ? '' : null"
                                        x-bind:style="hue(dot)"
                                        data-atom-calendar-dot
                                        class="size-1.5 shrink-0 rounded-full"></span>
                                    </template>

                                    <span x-show="day.extra" x-text="'+' + day.extra" class="text-xs leading-none text-zinc-500 dark:text-zinc-400"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- The day list: every item of one day. A manual popover, so a second click on the same day closes it
             rather than light-dismissing and reopening it. --}}
        <div
        popover="manual"
        role="dialog"
        tabindex="-1"
        x-ref="popover"
        x-bind:aria-label="popoverLabel"
        data-atom-calendar-popover
        class="w-64 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0 text-zinc-900 dark:text-zinc-100 shadow-xs outline-hidden">
            <div class="flex items-start justify-between gap-2 p-3 pb-2">
                <div>
                    <div x-text="popoverDate ? weekdayName(popoverDate) : ''" class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></div>
                    <div
                    x-text="popoverDate ? Number(popoverDate.slice(8, 10)) : ''"
                    x-bind:class="popoverDate && popoverDate === payload?.today ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-900 dark:text-zinc-100'"
                    class="inline-flex size-9 items-center justify-center rounded-full text-2xl font-semibold tabular-nums"></div>
                </div>

                <atom:button variant="ghost" size="sm" icon="close" :aria-label="t('Close')" x-on:click="closeDay()" />
            </div>

            <div data-atom-scroll-region class="space-y-0.5 p-2 pt-0">
                <template x-for="chip in popoverEntries" :key="chip.key">
                    <div>
                        <atom:calendar.chip />
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Icons are Blade components and cannot be rendered from JS: the server prints one template for each
         icon the items use, and the engine clones it. This sits in the morphable part of the root, so a new
         icon arrives with the payload it belongs to. Each template is keyed by its icon: unkeyed, the morph
         matches them by position and patches the name without the glyph inside. --}}
    <div hidden>
        @foreach ($icons as $icon)
            <template wire:key="atom-calendar-icon-{{ $icon }}" data-atom-calendar-icon="{{ $icon }}"><x-dynamic-component :component="'atom::icon.'.$icon" class="size-3" /></template>
        @endforeach
    </div>
</div>
