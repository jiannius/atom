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
    'name' => null,
])

@php
// The props of the event-calendar wrapper this component replaced. Passed now they would be printed as attributes and ignored, so the page would
// show a month view where it asked for something else; a render that fails names them instead. `name` is not here: it is still a prop.
$legacy = collect(['modes', 'periods'])->filter(fn ($prop) => $attributes->has($prop))->values();

if ($legacy->isNotEmpty()) {
    throw new \LogicException(
        '<atom:calendar> no longer takes '.$legacy->map(fn ($prop) => '`'.$prop.'`')->implode(' or ').': the event-calendar wrapper is gone. '
        .'Use `views` (month, week, day) where `periods` chose the views; there is no timeline mode, so drop `modes`. '
        .'The item shape and the events changed too: see "Upgrading from the event-calendar wrapper" in the README.'
    );
}

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
    // an explicit null falls back to the default 3 in @props, so no limit is false, 0 or none
    'max' => $max,
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

// What the ids inside the calendar are built from (the popover a day opens, the body a view switch controls, the title a grid is named by).
// Derived and never random: an id is Livewire's morph key where it falls back to one, so a new one each render would replace the node.
// A `name` is the host's own handle, as a slug and a hash of the name as written (the slug alone is the same for "Team plan" and "team-plan",
// and empty for a name that is all punctuation); a bound calendar is its component and its property; one with neither takes a number from
// the engine when it starts (`n1`, `n2`), which is the same for as long as the page lives. Two calendars bound to the same property of
// the same component need a `name` each, or they have one id between them.
$uid = $name ? 'atom-calendar-'.collect([\Illuminate\Support\Str::slug((string) $name), substr(md5((string) $name), 0, 8)])->filter()->implode('-') : ($model && $component ? $attributes->fieldId('atom-calendar', method_exists($component, 'getId') ? $component->getId() : $component->getName(), $model) : null);

// A colour is decoration: tint behind a chip, an accent edge and a dot, each readable against its
// ground in light and dark. Chip text stays neutral. The strings are written out here, not built in
// JS, so the host's Tailwind finds them. A host adds or replaces a colour in config('atom.calendar.colors')
// with the same three keys, and has to make its own build see those classes.
$palette = [
    'red' => ['tint' => 'bg-red-100 dark:bg-red-500/20', 'accent' => 'border-red-600 dark:border-red-400', 'dot' => 'bg-red-600 dark:bg-red-400'],
    'orange' => ['tint' => 'bg-orange-100 dark:bg-orange-500/20', 'accent' => 'border-orange-600 dark:border-orange-400', 'dot' => 'bg-orange-600 dark:bg-orange-400'],
    'yellow' => ['tint' => 'bg-yellow-100 dark:bg-yellow-500/20', 'accent' => 'border-yellow-700 dark:border-yellow-400', 'dot' => 'bg-yellow-700 dark:bg-yellow-400'],
    'green' => ['tint' => 'bg-green-100 dark:bg-green-500/20', 'accent' => 'border-green-700 dark:border-green-400', 'dot' => 'bg-green-700 dark:bg-green-400'],
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

$icons = collect($payload['items'])->pluck('icon')->push('warning', 'loading')->filter()->unique()->values();
@endphp

<div
x-data="calendar"
@if ($model) data-atom-calendar-model="{{ $model }}" @endif
@if ($uid) data-atom-calendar-uid="{{ $uid }}" @endif
data-atom-calendar-palette="{{ json_encode($palette, JSON_THROW_ON_ERROR) }}"
{{-- The clock is an attribute of its own and not part of the payload: the engine lays the month out again whenever
     the payload changes, which a clock that moves every second would make every render. The week and day views'
     now-indicator reads this one. --}}
data-atom-calendar-now="{{ $calendar->clock() }}"
data-atom-calendar-payload="{{ json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
data-atom-calendar
{{ $attributes->whereDoesntStartWith('wire:model')->class([
    'relative min-w-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs',
]) }}>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-700 p-3">
        {{-- Everything the engine drives is wire:ignore: Livewire re-renders around it and never into it. --}}
        <div wire:ignore class="flex min-w-0 items-center gap-3">
            @if ($model)
                {{-- Ghost, so the buttons take the toolbar's own ground in either theme: the default button is a white box, a slab in dark mode. --}}
                <div class="flex items-center gap-1">
                    <atom:button variant="ghost" size="sm" icon="left" :aria-label="t('Previous')" x-on:click="prev()" />
                    <atom:button variant="ghost" size="sm" x-on:click="today()">{{ t('Today') }}</atom:button>
                    <atom:button variant="ghost" size="sm" icon="right" :aria-label="t('Next')" x-on:click="next()" />
                </div>

                <atom:date-picker x-model="jump">
                    <button type="button" x-bind:aria-label="title.text + ', ' + (labels.ui?.choose_date ?? '')" data-atom-calendar-title class="flex items-center gap-1.5 rounded-sm text-base @2xl:text-lg outline-offset-2 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
                        <span x-text="title.month" class="font-semibold text-zinc-900 dark:text-white"></span>
                        <span x-cloak x-show="title.year" x-text="title.year" data-atom-calendar-title-year class="font-normal text-zinc-500 dark:text-zinc-400"></span>
                        <atom:icon.dropdown class="size-4 text-zinc-500 dark:text-zinc-400" />
                    </button>
                </atom:date-picker>
            @else
                <div data-atom-calendar-title class="flex items-center gap-1.5 text-base @2xl:text-lg">
                    <span x-text="title.month" class="font-semibold text-zinc-900 dark:text-white"></span>
                    <span x-cloak x-show="title.year" x-text="title.year" data-atom-calendar-title-year class="font-normal text-zinc-500 dark:text-zinc-400"></span>
                </div>
            @endif

            <span x-cloak x-show="loading" class="inline-flex text-zinc-500 dark:text-zinc-400"><atom:icon.loading class="size-4" /></span>

            {{-- Said when the title changes (a month, a week, a day), not when the payload does: `liveTitle` is written only for a new text. --}}
            <div x-text="liveTitle" x-bind:id="uid + '-title'" role="status" aria-live="polite" data-atom-calendar-sr></div>

            {{-- Said while a selection waits for the host: its placeholder is hidden from assistive tech, so this is what tells that something is being made. A region
                 of its own: the title's is written only when the title changes, and must not be read out for this. --}}
            <div x-text="announcement" role="status" aria-live="polite" data-atom-calendar-sr data-atom-calendar-announce></div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @endif

            <div wire:ignore x-cloak x-show="views.length > 1" role="group" x-bind:aria-label="labels.ui?.view" class="inline-flex items-center rounded-md bg-zinc-100 p-1 dark:bg-zinc-700">
                <template x-for="name in views" :key="name">
                    <button
                    type="button"
                    x-on:click="setView(name)"
                    x-bind:aria-pressed="view === name ? 'true' : 'false'"
                    x-bind:aria-controls="uid + '-body'"
                    x-bind:class="view === name ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 dark:text-zinc-300'"
                    class="h-6 rounded-sm px-3 text-sm font-medium outline-offset-1 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"
                    x-text="labels.ui?.[name]"></button>
                </template>
            </div>
        </div>
    </div>

    <div wire:ignore x-bind:id="uid + '-body'" data-atom-calendar-body>
        {{-- Shown by atom.css where the month is laid out narrow, and by nothing else: the engine asks it whether the narrow layout is on (isNarrow()),
             so that is decided in one place, by the stylesheet's own container rule. Not `hidden`: a Preflight's [hidden] rule would beat what shows it. --}}
        <div aria-hidden="true" data-atom-calendar-narrow></div>

        <div x-cloak x-show="view === 'month'" role="grid" x-bind:aria-labelledby="uid + '-title'">
            <div role="row" data-atom-calendar-weekdays>
                <template x-for="day in labels.weekdays" :key="day.key">
                    <div role="columnheader" x-text="day.short" x-bind:aria-label="day.long" class="py-2 text-center text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></div>
                </template>
            </div>

            <div x-on:pointerdown="selectStart($event, 'month')" data-atom-calendar-weeks>
                <template x-for="week in weeks" :key="week.key">
                    <div data-atom-calendar-week>
                        <div role="row" data-atom-calendar-week-bg>
                            <template x-for="day in week.days" :key="day.date">
                                <div
                                role="gridcell"
                                x-bind:data-date="day.date"
                                x-bind:aria-label="dayLabel(day)"
                                x-bind:aria-current="day.today ? 'date' : null"
                                x-bind:aria-haspopup="day.count > 0 ? 'dialog' : null"
                                x-bind:aria-expanded="day.count > 0 ? String(popoverDate === day.date && popoverSource === 'day') : null"
                                x-bind:aria-controls="day.count > 0 ? uid + '-popover' : null"
                                x-bind:tabindex="day.date === tabDate ? 0 : -1"
                                x-bind:class="{
                                    'bg-zinc-50 dark:bg-zinc-900/40': day.outside,
                                    'bg-zinc-50 dark:bg-zinc-900/30': day.weekend && !day.outside,
                                    'cursor-pointer': day.count > 0 && !config.selectable,
                                }"
                                x-on:click="dayClick(day, $event)"
                                x-on:focus="focusDate = day.date"
                                x-on:keydown="dayKey(day, $event)"
                                data-atom-calendar-day
                                class="border-t border-zinc-200 dark:border-zinc-700 not-first:border-l outline-offset-[-2px] focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"></div>
                            </template>
                        </div>

                        {{-- A gesture in progress, drawn from the engine's `preview`: a box over the days selected (or waiting for the
                             host), under the chips, and the ghost of an item being moved or resized among them. Neither takes a pointer. --}}
                        <template x-if="week.selection">
                            <div x-bind:style="spanStyle(week.selection)" x-bind:data-kind="week.selection.kind" aria-hidden="true" data-atom-calendar-selection class="rounded-md bg-zinc-900/5 ring-1 ring-inset ring-zinc-500 dark:bg-white/[.07] dark:ring-zinc-400"></div>
                        </template>

                        <div data-atom-calendar-chips>
                            <template x-for="day in week.days" :key="'number-' + day.date">
                                <div x-bind:style="{ gridColumn: String(day.col), gridRow: '1' }" data-atom-calendar-number class="flex items-center">
                                    <span
                                    x-text="day.number"
                                    x-bind:class="day.today ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (day.outside || day.past ? 'text-zinc-600 dark:text-zinc-400' : 'text-zinc-900 dark:text-zinc-100')"
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
                                aria-haspopup="dialog"
                                x-bind:aria-expanded="String(popoverDate === more.date && popoverSource === 'more')"
                                x-bind:aria-controls="uid + '-popover'"
                                x-text="moreLabel(more.count)"
                                data-atom-calendar-more
                                class="h-5 w-full rounded-sm px-1.5 text-left text-xs font-medium text-zinc-600 outline-offset-1 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700/60 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"></button>
                            </template>

                            <template x-if="week.ghost">
                                <div x-bind:style="slotStyle(week.ghost)" x-bind:data-kind="week.ghost.kind" x-bind:data-before="week.ghost.before ? '' : null" x-bind:data-after="week.ghost.after ? '' : null" aria-hidden="true" data-atom-calendar-ghost class="rounded-sm bg-white shadow-lg ring-1 ring-zinc-900/10 dark:bg-zinc-800 dark:ring-white/20">
                                    <template x-if="week.ghost.kind === 'pending'">
                                        <div data-atom-calendar-pending class="flex h-5 min-w-0 items-center gap-1 rounded-sm px-1.5 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-500 dark:text-zinc-200 dark:ring-zinc-400">
                                            <span x-effect="setIcon($el, 'loading')" class="inline-flex shrink-0"></span>
                                            <span x-text="week.ghost.title" class="truncate"></span>
                                        </div>
                                    </template>

                                    <template x-if="week.ghost.kind !== 'pending'">
                                        <div
                                        x-bind:class="[colorClass(week.ghost, 'tint'), week.ghost.danger ? 'border-red-600 dark:border-red-400' : colorClass(week.ghost, 'accent')]"
                                        x-bind:style="hue(week.ghost)"
                                        x-bind:data-atom-calendar-hex="isHex(week.ghost) && !week.ghost.danger ? '' : null"
                                        data-atom-calendar-ghost-body
                                        class="relative flex h-5 min-w-0 items-center gap-1 rounded-sm border-l-[3px] px-1.5 text-xs font-medium">
                                            <span x-text="week.ghost.title" x-bind:class="week.ghost.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" class="truncate"></span>
                                            <span x-show="week.ghost.kind === 'resize'" aria-hidden="true" class="absolute inset-y-1 right-1 w-1 rounded-full bg-zinc-600 dark:bg-zinc-300"></span>
                                        </div>
                                    </template>
                                </div>
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

                                    <span x-show="day.extra" x-text="'+' + day.extra" class="text-xs leading-none text-zinc-600 dark:text-zinc-400"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- The week and day views: one scroller holds a header that stays on top (the days, then the all-day row) and
             a body of 24 hours, so a narrow screen scrolls the week sideways with its header and its hour gutter
             (which stays at the left) still in step. Where things sit is atom.css's: the hour height, the lanes and the
             now line are positioned there and by percentages from the layout, never by utilities. --}}
        <div x-cloak x-show="view !== 'month'" role="group" x-bind:aria-labelledby="uid + '-title'" x-bind:style="{ '--atom-calendar-columns': grid.columns.length }" data-atom-calendar-grid>
            <div data-atom-calendar-grid-inner>
                <div data-atom-calendar-grid-top class="bg-white dark:bg-zinc-800">
                    <div data-atom-calendar-grid-row class="border-b border-zinc-200 dark:border-zinc-700">
                        <div data-atom-calendar-gutter class="bg-white dark:bg-zinc-800"></div>

                        {{-- A day's header opens that day in the day view. In the day view it is the title of the one column there is, and there is
                             nothing to press: a group named for the day, which the focus can be put on (not a tab stop). --}}
                        <template x-for="column in (view === 'day' ? [] : grid.columns)" :key="column.date">
                            <button
                            type="button"
                            x-bind:data-date="column.date"
                            x-bind:aria-label="(labels.ui?.open_day ?? '').replace(':date', () => longDate(column.date))"
                            x-on:click="showDay(column.date)"
                            data-atom-calendar-column-head
                            class="flex cursor-pointer flex-col items-center gap-0.5 border-l border-zinc-200 dark:border-zinc-700 py-2 outline-offset-[-2px] focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
                                <span x-text="weekdayName(column.date)" class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></span>
                                <span
                                x-text="column.number"
                                x-bind:class="column.today ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (column.past ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-900 dark:text-zinc-100')"
                                class="inline-flex size-8 items-center justify-center rounded-full text-lg font-semibold tabular-nums"></span>
                            </button>
                        </template>

                        <template x-for="column in (view === 'day' ? grid.columns : [])" :key="column.date">
                            <div
                            role="group"
                            tabindex="-1"
                            x-bind:data-date="column.date"
                            x-bind:aria-label="longDate(column.date)"
                            data-atom-calendar-column-head
                            class="flex flex-col items-center gap-0.5 border-l border-zinc-200 dark:border-zinc-700 py-2 outline-offset-[-2px] focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
                                <span x-text="weekdayName(column.date)" aria-hidden="true" class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></span>
                                <span
                                x-text="column.number"
                                aria-hidden="true"
                                x-bind:class="column.today ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (column.past ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-900 dark:text-zinc-100')"
                                class="inline-flex size-8 items-center justify-center rounded-full text-lg font-semibold tabular-nums"></span>
                            </div>
                        </template>
                    </div>

                    <div data-atom-calendar-grid-row data-atom-calendar-allday class="border-b border-zinc-200 dark:border-zinc-700">
                        <div data-atom-calendar-gutter class="bg-white dark:bg-zinc-800 pr-2 pt-2 text-right text-xs text-zinc-500 dark:text-zinc-400">
                            <span x-text="labels.ui?.all_day"></span>
                        </div>

                        <div x-on:pointerdown="selectStart($event, 'allday')" data-atom-calendar-allday-area>
                            <div data-atom-calendar-allday-bg aria-hidden="true">
                                <template x-for="column in grid.columns" :key="'allday-' + column.date">
                                    <div x-bind:data-date="column.date" x-bind:class="column.weekend ? 'bg-zinc-50 dark:bg-zinc-900/30' : ''" class="border-l border-zinc-200 dark:border-zinc-700"></div>
                                </template>
                            </div>

                            <div data-atom-calendar-allday-chips>
                                <template x-for="chip in grid.allDay.chips" :key="chip.key">
                                    <div x-bind:style="slotStyle(chip)" data-atom-calendar-slot>
                                        <atom:calendar.chip />
                                    </div>
                                </template>

                                <template x-for="more in grid.allDay.more" :key="more.key">
                                    <button
                                    type="button"
                                    x-bind:style="slotStyle(more)"
                                    x-on:click="openDay(more.date, $event.currentTarget)"
                                    aria-haspopup="dialog"
                                    x-bind:aria-expanded="String(popoverDate === more.date && popoverSource === 'more')"
                                    x-bind:aria-controls="uid + '-popover'"
                                    x-text="moreLabel(more.count)"
                                    data-atom-calendar-more
                                    class="h-5 w-full rounded-sm px-1.5 text-left text-xs font-medium text-zinc-600 outline-offset-1 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700/60 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white"></button>
                                </template>

                                {{-- An all-day item being moved or resized, over the days it would then cover (the engine's `preview`): the ghost the month's weeks have,
                                     among the bars, taking no pointer. The item stays where it is, lifted. --}}
                                <template x-if="allDayItemGhost">
                                    <div x-bind:style="slotStyle(allDayItemGhost)" x-bind:data-kind="allDayItemGhost.kind" x-bind:data-before="allDayItemGhost.before ? '' : null" x-bind:data-after="allDayItemGhost.after ? '' : null" aria-hidden="true" data-atom-calendar-ghost class="rounded-sm bg-white shadow-lg ring-1 ring-zinc-900/10 dark:bg-zinc-800 dark:ring-white/20">
                                        <div
                                        x-bind:class="[colorClass(allDayItemGhost, 'tint'), allDayItemGhost.danger ? 'border-red-600 dark:border-red-400' : colorClass(allDayItemGhost, 'accent')]"
                                        x-bind:style="hue(allDayItemGhost)"
                                        x-bind:data-atom-calendar-hex="isHex(allDayItemGhost) && !allDayItemGhost.danger ? '' : null"
                                        data-atom-calendar-ghost-body
                                        class="relative flex h-5 min-w-0 items-center gap-1 rounded-sm border-l-[3px] px-1.5 text-xs font-medium">
                                            <span x-text="allDayItemGhost.title" x-bind:class="allDayItemGhost.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" class="truncate"></span>
                                            <span x-show="allDayItemGhost.kind === 'resize'" aria-hidden="true" class="absolute inset-y-1 right-1 w-1 rounded-full bg-zinc-600 dark:bg-zinc-300"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- A selection in the all-day row, or an item waiting for the host there (the engine's `preview`, as in the columns): a box
                                 over the days. In front of the bars, so the placeholder is seen, and it takes no pointer. --}}
                            <div aria-hidden="true" data-atom-calendar-allday-over>
                                <template x-if="allDayPreview">
                                    <div x-bind:style="spanStyle(allDayPreview)" x-bind:data-kind="allDayPreview.kind" data-atom-calendar-selection class="rounded-md bg-zinc-900/5 ring-1 ring-inset ring-zinc-500 dark:bg-white/[.07] dark:ring-zinc-400">
                                        <template x-if="allDayPreview.kind === 'pending'">
                                            <div data-atom-calendar-pending class="m-1 flex h-5 min-w-0 items-center gap-1 rounded-sm bg-white px-1.5 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-500 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-400">
                                                <span x-effect="setIcon($el, 'loading')" class="inline-flex shrink-0"></span>
                                                <span x-text="allDayPreview.title" class="truncate"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-on:pointerdown="selectStart($event, 'grid')" data-atom-calendar-grid-body>
                    <div data-atom-calendar-gutter aria-hidden="true" class="bg-white dark:bg-zinc-800">
                        <template x-for="hour in hours" :key="hour">
                            <div data-atom-calendar-hour>
                                <span x-text="hourLabel(hour)" data-atom-calendar-hour-label class="pr-2 text-xs tabular-nums text-zinc-500 dark:text-zinc-400"></span>
                            </div>
                        </template>
                    </div>

                    <template x-for="column in grid.columns" :key="column.date">
                        <div
                        role="group"
                        x-bind:aria-label="dayLabel(column, column.blocks.length)"
                        x-bind:aria-current="column.today ? 'date' : null"
                        x-bind:data-date="column.date"
                        x-bind:class="column.weekend ? 'bg-zinc-50 dark:bg-zinc-900/30' : ''"
                        data-atom-calendar-column
                        class="border-l border-zinc-200 dark:border-zinc-700">
                            <template x-for="chip in column.blocks" :key="chip.key">
                                <div x-bind:style="blockStyle(chip)" data-atom-calendar-block>
                                    <atom:calendar.chip />
                                </div>
                            </template>

                            <template x-if="nowLine(column)">
                                <div x-bind:style="{ top: nowLine(column).top }" x-bind:data-time="nowLine(column).time" aria-hidden="true" data-atom-calendar-now-line class="bg-red-500 dark:bg-red-400">
                                    <span class="bg-red-500 dark:bg-red-400"></span>
                                </div>
                            </template>

                            {{-- A gesture in progress (the engine's `preview`): the selection, the item waiting for the host, or
                                 the ghost of one being moved or resized. Over the blocks and the now line, and it takes no pointer. --}}
                            <template x-for="shape in (previews[column.date] ?? [])" :key="shape.key">
                                <div x-bind:style="blockStyle(shape)" x-bind:data-kind="shape.kind" x-bind:data-before="shape.before ? '' : null" x-bind:data-after="shape.after ? '' : null" aria-hidden="true" data-atom-calendar-preview>
                                    <template x-if="shape.kind === 'select'">
                                        {{-- a shape of its own is on a ground of its own: what is under a selection or an item waiting for the host (an item in the same hours) must not show through its label --}}
                                        <div class="h-full rounded-md bg-white dark:bg-zinc-800">
                                            <div class="h-full overflow-hidden rounded-md bg-zinc-900/5 px-1.5 py-0.5 text-xs font-medium tabular-nums text-zinc-700 ring-1 ring-inset ring-zinc-500 dark:bg-white/10 dark:text-zinc-200 dark:ring-zinc-400" x-text="timeRange(shape)"></div>
                                        </div>
                                    </template>

                                    <template x-if="shape.kind === 'pending'">
                                        <div data-atom-calendar-pending class="flex h-full flex-col overflow-hidden rounded-md bg-white px-1.5 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 ring-1 ring-inset ring-zinc-500 dark:text-zinc-200 dark:ring-zinc-400">
                                            <span class="flex min-w-0 items-center gap-1 font-semibold">
                                                <span x-effect="setIcon($el, 'loading')" class="inline-flex shrink-0"></span>
                                                <span x-text="shape.title || timeRange(shape)" class="truncate"></span>
                                            </span>
                                        </div>
                                    </template>

                                    <template x-if="shape.kind === 'move' || shape.kind === 'resize'">
                                        <div class="relative h-full rounded-md bg-white shadow-lg ring-1 ring-zinc-900/10 dark:bg-zinc-800 dark:ring-white/20">
                                            <div
                                            x-bind:class="[colorClass(shape, 'tint'), shape.danger ? 'border-red-600 dark:border-red-400' : colorClass(shape, 'accent')]"
                                            x-bind:style="hue(shape)"
                                            x-bind:data-atom-calendar-hex="isHex(shape) && !shape.danger ? '' : null"
                                            x-bind:data-compact="shape.compact ? '' : null"
                                            data-atom-calendar-ghost-body
                                            class="flex h-full min-w-0 flex-col overflow-hidden rounded-md border-l-[3px] px-1.5 py-0.5 text-xs">
                                                <span class="flex min-w-0 items-center gap-1">
                                                    <span x-text="shape.title" x-bind:class="shape.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" data-atom-calendar-item-title class="truncate font-semibold"></span>
                                                    <span x-show="shape.compact" x-text="'· ' + timeLabel(shape)" data-atom-calendar-item-time class="shrink-0 font-normal tabular-nums text-zinc-600 dark:text-zinc-300"></span>
                                                </span>
                                                <span x-show="!shape.compact" x-text="timeRange(shape)" data-atom-calendar-item-time class="truncate font-normal tabular-nums text-zinc-600 dark:text-zinc-300"></span>
                                            </div>

                                            <template x-if="shape.kind === 'resize'">
                                                <div>
                                                    <span aria-hidden="true" class="absolute inset-x-0 bottom-0.5 mx-auto h-1 w-6 rounded-full bg-zinc-600 dark:bg-zinc-300"></span>
                                                    <span x-text="endLabel(shape)" class="absolute -bottom-3 right-1 rounded-sm bg-zinc-900 px-1.5 py-0.5 text-xs font-medium tabular-nums text-white dark:bg-white dark:text-zinc-900"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- the hour lines, over the columns' tint and under the blocks --}}
                    <div data-atom-calendar-lines aria-hidden="true">
                        <template x-for="hour in hours" :key="hour">
                            <div data-atom-calendar-line x-bind:class="hour === 0 ? '' : 'border-t border-zinc-200 dark:border-zinc-700'">
                                <div data-atom-calendar-half class="border-t border-dashed border-zinc-100 dark:border-zinc-700/50"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- The day list: every item of one day. A manual popover, so a second click on the same day closes it
             rather than light-dismissing and reopening it. --}}
        <div
        popover="manual"
        role="dialog"
        tabindex="-1"
        x-ref="popover"
        x-bind:id="uid + '-popover'"
        x-bind:aria-label="popoverLabel"
        x-on:keydown.tab="popoverTab($event)"
        data-atom-calendar-popover
        class="w-64 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0 text-zinc-900 dark:text-zinc-100 shadow-xs outline-hidden">
            <div class="flex items-start justify-between gap-2 p-3 pb-2">
                <div>
                    <div x-text="popoverDate ? weekdayName(popoverDate) : ''" class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></div>
                    <div
                    x-text="popoverDate ? Number(popoverDate.slice(8, 10)) : ''"
                    x-bind:class="popoverDate && popoverDate === todayDate ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-900 dark:text-zinc-100'"
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
