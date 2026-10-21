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
// What the event-calendar wrapper this component replaced took on the tag. Passed now it would be printed as an attribute and ignored: the page
// would show a month view where it asked for something else, a listener would never be called, a binding would never be written. A render that
// fails names each one instead. `name` is not here: it is still a prop. A dispatcher elsewhere in the page (an add-calendar-events dispatch) is
// not on the tag, so it cannot be seen from here; the README's upgrade section says to search for it.
$legacyProps = [
    'modes' => 'there is no timeline mode, so drop it (the views are `views`: month, week, day)',
    'periods' => 'use `views` (month, week, day)',
];
$legacyEvents = [
    'calendar-date-clicked' => '`calendar-select` (a click is a one-day select; needs `selectable`)',
    'calendar-date-selected' => '`calendar-select`',
    'calendar-event-clicked' => '`calendar-item-click`',
    'calendar-event-dropped' => '`calendar-item-move`',
    'calendar-event-resized' => '`calendar-item-resize`',
    'calendar-changed' => 'nothing: the `items` closure is called for the window on every render',
    'calendar-mounted' => 'nothing: there is no calendar object to wait for',
];

$legacy = [];

foreach (array_keys($attributes->getAttributes()) as $attribute) {
    $lowered = strtolower((string) $attribute);

    if (isset($legacyProps[$lowered])) {
        $legacy[] = '`'.$attribute.'` is gone: '.$legacyProps[$lowered].'.';
    } elseif ($lowered === 'x-model' || str_starts_with($lowered, 'x-model.') || str_starts_with($lowered, 'x-model:')) {
        $legacy[] = '`'.$attribute.'` no longer binds: the root is not `x-modelable`. Use `wire:model="date"` (a `Y-m-d` string), or none.';
    } elseif (preg_match('/^(?:x-on:|@)([^.]+)/', $lowered, $listener) && isset($legacyEvents[$listener[1]])) {
        $legacy[] = '`'.$attribute.'` listens for an event that is no longer dispatched. Use '.$legacyEvents[$listener[1]].'.';
    }
}

if ($legacy !== []) {
    throw new \LogicException(
        '<atom:calendar> still uses the event-calendar wrapper it replaced. '.implode(' ', $legacy).' '
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

// A colour is decoration: a tint behind a chip, an accent edge and a dot, each a tone's token (the tint and the ink of the tone,
// which hold their contrast in light and dark and follow the host's accent). Chip text stays neutral. The names a host already passes
// map onto the six tones the way Color::tone() does it: red is danger, orange and yellow are warning, green is success, blue is info,
// purple is the accent, and black is the foreground. The strings are written out here, not built in JS, so the host's Tailwind finds
// them. A host adds or replaces a colour in config('atom.calendar.colors') with the same three keys, and has to make its own build
// see those classes.
$palette = [
    'red' => ['tint' => 'bg-danger-bg', 'accent' => 'border-danger-fg', 'dot' => 'bg-danger-fg'],
    'orange' => ['tint' => 'bg-warning-bg', 'accent' => 'border-warning-fg', 'dot' => 'bg-warning-fg'],
    'yellow' => ['tint' => 'bg-warning-bg', 'accent' => 'border-warning-fg', 'dot' => 'bg-warning-fg'],
    'green' => ['tint' => 'bg-success-bg', 'accent' => 'border-success-fg', 'dot' => 'bg-success-fg'],
    'blue' => ['tint' => 'bg-info-bg', 'accent' => 'border-info-fg', 'dot' => 'bg-info-fg'],
    'purple' => ['tint' => 'bg-accent-soft', 'accent' => 'border-accent-ink', 'dot' => 'bg-accent-ink'],
    'black' => ['tint' => 'bg-fg/10', 'accent' => 'border-fg', 'dot' => 'bg-fg'],
    'zinc' => ['tint' => 'bg-neutral-bg', 'accent' => 'border-fg-subtle', 'dot' => 'bg-fg-subtle'],
    'gray' => ['tint' => 'bg-neutral-bg', 'accent' => 'border-fg-subtle', 'dot' => 'bg-fg-subtle'],
];

// A name an item can never carry (see CalendarItem::colors(), the one list of the names that may) is no use in the palette.
$names = \Jiannius\Atom\Services\CalendarItem::colors();

foreach ((array) config('atom.calendar.colors', []) as $name => $classes) {
    if (in_array($name, $names, true) && is_array($classes)) {
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
    'relative min-w-0 overflow-hidden rounded-panel border border-border bg-surface text-fg',
]) }}>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-3">
        {{-- Everything the engine drives is wire:ignore: Livewire re-renders around it and never into it. --}}
        <div wire:ignore class="flex min-w-0 items-center gap-3">
            @if ($model)
                {{-- Ghost, so the buttons take the toolbar's own ground in either theme: the default button is a white box, a slab in dark mode. --}}
                <div class="flex items-center gap-1">
                    <atom:button variant="ghost" icon="left" :aria-label="t('Previous')" x-on:click="prev()" />
                    <atom:button variant="ghost" x-on:click="today()">{{ t('Today') }}</atom:button>
                    <atom:button variant="ghost" icon="right" :aria-label="t('Next')" x-on:click="next()" />
                </div>

                <atom:date-picker x-model="jump">
                    <button type="button" x-bind:aria-label="title.text + ', ' + (labels.ui?.choose_date ?? '')" data-atom-calendar-title class="flex items-center gap-1.5 rounded-sm text-base @2xl:text-lg outline-offset-2">
                        <span x-text="title.month" class="font-semibold text-fg"></span>
                        <span x-cloak x-show="title.year" x-text="title.year" data-atom-calendar-title-year class="font-normal text-fg-muted"></span>
                        <atom:icon.dropdown class="size-4 text-fg-muted" />
                    </button>
                </atom:date-picker>
            @else
                <div data-atom-calendar-title class="flex items-center gap-1.5 text-base @2xl:text-lg">
                    <span x-text="title.month" class="font-semibold text-fg"></span>
                    <span x-cloak x-show="title.year" x-text="title.year" data-atom-calendar-title-year class="font-normal text-fg-muted"></span>
                </div>
            @endif

            <span x-cloak x-show="loading" class="inline-flex text-fg-muted"><atom:icon.loading class="size-4" /></span>

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

            <div wire:ignore x-cloak x-show="views.length > 1" role="group" x-bind:aria-label="labels.ui?.view" class="inline-flex h-control items-center rounded-control bg-surface-sunken p-0.5">
                <template x-for="name in views" :key="name">
                    <button
                    type="button"
                    x-on:click="setView(name)"
                    x-bind:aria-pressed="view === name ? 'true' : 'false'"
                    x-bind:aria-controls="uid + '-body'"
                    x-bind:class="view === name ? 'bg-surface text-fg ring-1 ring-inset ring-border-strong' : 'text-fg-muted hover:text-fg'"
                    class="h-full rounded-nav px-3 text-sm font-medium outline-offset-1"
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
                    <div role="columnheader" x-text="day.short" x-bind:aria-label="day.long" class="py-2 text-center text-xs font-medium uppercase tracking-wide text-fg-muted"></div>
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
                                x-bind:aria-label="day.label"
                                x-bind:aria-current="day.today ? 'date' : null"
                                x-bind:aria-haspopup="day.count > 0 ? 'dialog' : null"
                                x-effect="day.expanded === null ? $el.removeAttribute('aria-expanded') : $el.setAttribute('aria-expanded', day.expanded)"
                                x-bind:aria-controls="day.count > 0 ? uid + '-popover' : null"
                                x-bind:tabindex="day.date === tabDate ? 0 : -1"
                                x-bind:class="{
                                    'bg-surface-sunken': day.outside,
                                    'bg-surface-sunken/50': day.weekend && !day.outside,
                                    'cursor-pointer': day.clickable,
                                }"
                                x-on:click="dayClick(day, $event)"
                                x-on:focus="focusDate = day.date"
                                x-on:keydown="dayKey(day, $event)"
                                data-atom-calendar-day
                                class="border-t border-border not-first:border-l outline-offset-[-2px]"></div>
                            </template>
                        </div>

                        {{-- A gesture in progress, drawn from the engine's `preview`: a box over the days selected (or waiting for the
                             host), under the chips, and the ghost of an item being moved or resized among them. Neither takes a pointer. --}}
                        <template x-if="week.selection">
                            <div x-bind:style="spanStyle(week.selection)" x-bind:data-kind="week.selection.kind" aria-hidden="true" data-atom-calendar-selection class="rounded-md bg-accent-soft ring-1 ring-inset ring-accent-ink"></div>
                        </template>

                        <div data-atom-calendar-chips>
                            <template x-for="day in week.days" :key="'number-' + day.date">
                                <div x-bind:style="{ gridColumn: String(day.col), gridRow: '1' }" data-atom-calendar-number class="flex items-center">
                                    <span
                                    x-text="day.number"
                                    x-bind:class="day.today ? 'bg-accent-ink text-surface' : (day.outside || day.past ? 'text-fg-muted' : 'text-fg')"
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
                                x-text="more.label"
                                data-atom-calendar-more
                                class="h-5 w-full rounded-sm px-1.5 text-left text-xs font-medium text-fg-muted outline-offset-1 hover:bg-fg/5"></button>
                            </template>

                            <template x-if="week.ghost">
                                <div x-bind:style="slotStyle(week.ghost)" x-bind:data-kind="week.ghost.kind" x-bind:data-before="week.ghost.before ? '' : null" x-bind:data-after="week.ghost.after ? '' : null" aria-hidden="true" data-atom-calendar-ghost class="rounded-sm bg-surface-raised shadow-pop ring-1 ring-border-strong">
                                    <template x-if="week.ghost.kind === 'pending'">
                                        <div data-atom-calendar-pending class="flex h-5 min-w-0 items-center gap-1 rounded-sm px-1.5 text-xs font-medium text-fg ring-1 ring-inset ring-accent-ink">
                                            <span x-effect="setIcon($el, 'loading')" class="inline-flex shrink-0"></span>
                                            <span x-text="week.ghost.title" class="truncate"></span>
                                        </div>
                                    </template>

                                    <template x-if="week.ghost.kind !== 'pending'">
                                        <div
                                        x-bind:class="[colorClass(week.ghost, 'tint'), week.ghost.danger ? 'border-danger-fg' : colorClass(week.ghost, 'accent')]"
                                        x-bind:style="hue(week.ghost)"
                                        x-bind:data-atom-calendar-hex="isHex(week.ghost) && !week.ghost.danger ? '' : null"
                                        data-atom-calendar-ghost-body
                                        class="relative flex h-5 min-w-0 items-center gap-1 rounded-sm border-l-[3px] px-1.5 text-xs font-medium">
                                            <span x-text="week.ghost.title" x-bind:class="week.ghost.danger ? 'text-danger-fg' : 'text-fg'" class="truncate"></span>
                                            <span x-show="week.ghost.kind === 'resize'" aria-hidden="true" class="absolute inset-y-1 right-1 w-1 rounded-full bg-fg-muted"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-for="day in week.days" :key="'dots-' + day.date">
                                <div x-show="day.dots.length" x-bind:style="{ gridColumn: String(day.col), gridRow: '2' }" data-atom-calendar-dots>
                                    <template x-for="dot in day.dots" :key="dot.id">
                                        <span
                                        x-bind:class="dot.danger ? 'bg-danger-fg' : colorClass(dot, 'dot')"
                                        x-bind:data-atom-calendar-hex="isHex(dot) && !dot.danger ? '' : null"
                                        x-bind:style="hue(dot)"
                                        data-atom-calendar-dot
                                        class="size-1.5 shrink-0 rounded-full"></span>
                                    </template>

                                    <span x-show="day.extra" x-text="'+' + day.extra" class="text-xs leading-none text-fg-muted"></span>
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
                <div data-atom-calendar-grid-top class="bg-surface">
                    <div data-atom-calendar-grid-row class="border-b border-border">
                        <div data-atom-calendar-gutter class="bg-surface"></div>

                        {{-- A day's header opens that day in the day view. In the day view it is the title of the one column there is, and there is
                             nothing to press: a group named for the day, which the focus can be put on (not a tab stop). --}}
                        <template x-for="column in (view === 'day' ? [] : grid.columns)" :key="column.date">
                            <button
                            type="button"
                            x-bind:data-date="column.date"
                            x-bind:aria-label="(labels.ui?.open_day ?? '').replace(':date', () => longDate(column.date))"
                            x-on:click="showDay(column.date)"
                            data-atom-calendar-column-head
                            class="flex cursor-pointer flex-col items-center gap-0.5 border-l border-border py-2 outline-offset-[-2px]">
                                <span x-text="weekdayName(column.date)" class="text-xs font-medium uppercase tracking-wide text-fg-muted"></span>
                                <span
                                x-text="column.number"
                                x-bind:class="column.today ? 'bg-accent-ink text-surface' : (column.past ? 'text-fg-muted' : 'text-fg')"
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
                            class="flex flex-col items-center gap-0.5 border-l border-border py-2 outline-offset-[-2px]">
                                <span x-text="weekdayName(column.date)" aria-hidden="true" class="text-xs font-medium uppercase tracking-wide text-fg-muted"></span>
                                <span
                                x-text="column.number"
                                aria-hidden="true"
                                x-bind:class="column.today ? 'bg-accent-ink text-surface' : (column.past ? 'text-fg-muted' : 'text-fg')"
                                class="inline-flex size-8 items-center justify-center rounded-full text-lg font-semibold tabular-nums"></span>
                            </div>
                        </template>
                    </div>

                    <div data-atom-calendar-grid-row data-atom-calendar-allday class="border-b border-border">
                        <div data-atom-calendar-gutter class="bg-surface pr-2 pt-2 text-right text-xs text-fg-muted">
                            <span x-text="labels.ui?.all_day"></span>
                        </div>

                        <div x-on:pointerdown="selectStart($event, 'allday')" data-atom-calendar-allday-area>
                            <div data-atom-calendar-allday-bg aria-hidden="true">
                                <template x-for="column in grid.columns" :key="'allday-' + column.date">
                                    <div x-bind:data-date="column.date" x-bind:class="column.weekend ? 'bg-surface-sunken/50' : ''" class="border-l border-border"></div>
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
                                    x-text="more.label"
                                    data-atom-calendar-more
                                    class="h-5 w-full rounded-sm px-1.5 text-left text-xs font-medium text-fg-muted outline-offset-1 hover:bg-fg/5"></button>
                                </template>

                                {{-- An all-day item being moved or resized, over the days it would then cover (the engine's `preview`): the ghost the month's weeks have,
                                     among the bars, taking no pointer. The item stays where it is, lifted. --}}
                                <template x-if="allDayItemGhost">
                                    <div x-bind:style="slotStyle(allDayItemGhost)" x-bind:data-kind="allDayItemGhost.kind" x-bind:data-before="allDayItemGhost.before ? '' : null" x-bind:data-after="allDayItemGhost.after ? '' : null" aria-hidden="true" data-atom-calendar-ghost class="rounded-sm bg-surface-raised shadow-pop ring-1 ring-border-strong">
                                        <div
                                        x-bind:class="[colorClass(allDayItemGhost, 'tint'), allDayItemGhost.danger ? 'border-danger-fg' : colorClass(allDayItemGhost, 'accent')]"
                                        x-bind:style="hue(allDayItemGhost)"
                                        x-bind:data-atom-calendar-hex="isHex(allDayItemGhost) && !allDayItemGhost.danger ? '' : null"
                                        data-atom-calendar-ghost-body
                                        class="relative flex h-5 min-w-0 items-center gap-1 rounded-sm border-l-[3px] px-1.5 text-xs font-medium">
                                            <span x-text="allDayItemGhost.title" x-bind:class="allDayItemGhost.danger ? 'text-danger-fg' : 'text-fg'" class="truncate"></span>
                                            <span x-show="allDayItemGhost.kind === 'resize'" aria-hidden="true" class="absolute inset-y-1 right-1 w-1 rounded-full bg-fg-muted"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- A selection in the all-day row, or an item waiting for the host there (the engine's `preview`, as in the columns): a box
                                 over the days. In front of the bars, so the placeholder is seen, and it takes no pointer. --}}
                            <div aria-hidden="true" data-atom-calendar-allday-over>
                                <template x-if="allDayPreview">
                                    <div x-bind:style="spanStyle(allDayPreview)" x-bind:data-kind="allDayPreview.kind" data-atom-calendar-selection class="rounded-md bg-accent-soft ring-1 ring-inset ring-accent-ink">
                                        <template x-if="allDayPreview.kind === 'pending'">
                                            <div data-atom-calendar-pending class="m-1 flex h-5 min-w-0 items-center gap-1 rounded-sm bg-surface px-1.5 text-xs font-medium text-fg ring-1 ring-inset ring-accent-ink">
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
                    <div data-atom-calendar-gutter aria-hidden="true" class="bg-surface">
                        <template x-for="hour in hours" :key="hour">
                            <div data-atom-calendar-hour>
                                <span x-text="hourLabel(hour)" data-atom-calendar-hour-label class="pr-2 text-xs tabular-nums text-fg-muted"></span>
                            </div>
                        </template>
                    </div>

                    <template x-for="column in grid.columns" :key="column.date">
                        <div
                        role="group"
                        x-bind:aria-label="column.label"
                        x-bind:aria-current="column.today ? 'date' : null"
                        x-bind:data-date="column.date"
                        x-bind:class="column.weekend ? 'bg-surface-sunken/50' : ''"
                        data-atom-calendar-column
                        class="border-l border-border">
                            <template x-for="chip in column.blocks" :key="chip.key">
                                <div x-bind:style="blockStyle(chip)" data-atom-calendar-block>
                                    <atom:calendar.chip />
                                </div>
                            </template>

                            <template x-if="nowLine(column)">
                                <div x-bind:style="{ top: nowLine(column).top }" x-bind:data-time="nowLine(column).time" aria-hidden="true" data-atom-calendar-now-line class="bg-danger-fg">
                                    <span class="bg-danger-fg"></span>
                                </div>
                            </template>

                            {{-- A gesture in progress (the engine's `preview`): the selection, the item waiting for the host, or
                                 the ghost of one being moved or resized. Over the blocks and the now line, and it takes no pointer. --}}
                            <template x-for="shape in (previews[column.date] ?? [])" :key="shape.key">
                                <div x-bind:style="blockStyle(shape)" x-bind:data-kind="shape.kind" x-bind:data-before="shape.before ? '' : null" x-bind:data-after="shape.after ? '' : null" aria-hidden="true" data-atom-calendar-preview>
                                    <template x-if="shape.kind === 'select'">
                                        {{-- a shape of its own is on a ground of its own: what is under a selection or an item waiting for the host (an item in the same hours) must not show through its label --}}
                                        <div class="h-full rounded-md bg-surface">
                                            <div class="h-full overflow-hidden rounded-md bg-accent-soft px-1.5 py-0.5 text-xs font-medium tabular-nums text-fg ring-1 ring-inset ring-accent-ink" x-text="timeRange(shape)"></div>
                                        </div>
                                    </template>

                                    <template x-if="shape.kind === 'pending'">
                                        <div data-atom-calendar-pending class="flex h-full flex-col overflow-hidden rounded-md bg-surface px-1.5 py-0.5 text-xs text-fg ring-1 ring-inset ring-accent-ink">
                                            <span class="flex min-w-0 items-center gap-1 font-semibold">
                                                <span x-effect="setIcon($el, 'loading')" class="inline-flex shrink-0"></span>
                                                <span x-text="shape.title || timeRange(shape)" class="truncate"></span>
                                            </span>
                                        </div>
                                    </template>

                                    <template x-if="shape.kind === 'move' || shape.kind === 'resize'">
                                        <div class="relative h-full rounded-md bg-surface-raised shadow-pop ring-1 ring-border-strong">
                                            <div
                                            x-bind:class="[colorClass(shape, 'tint'), shape.danger ? 'border-danger-fg' : colorClass(shape, 'accent')]"
                                            x-bind:style="hue(shape)"
                                            x-bind:data-atom-calendar-hex="isHex(shape) && !shape.danger ? '' : null"
                                            x-bind:data-compact="shape.compact ? '' : null"
                                            data-atom-calendar-ghost-body
                                            class="flex h-full min-w-0 flex-col overflow-hidden rounded-md border-l-[3px] px-1.5 py-0.5 text-xs">
                                                <span class="flex min-w-0 items-center gap-1">
                                                    <span x-text="shape.title" x-bind:class="shape.danger ? 'text-danger-fg' : 'text-fg'" data-atom-calendar-item-title class="truncate font-semibold"></span>
                                                    <span x-show="shape.compact" x-text="'· ' + timeLabel(shape)" data-atom-calendar-item-time class="shrink-0 font-normal tabular-nums text-fg"></span>
                                                </span>
                                                <span x-show="!shape.compact" x-text="timeRange(shape)" data-atom-calendar-item-time class="truncate font-normal tabular-nums text-fg"></span>
                                            </div>

                                            <template x-if="shape.kind === 'resize'">
                                                <div>
                                                    <span aria-hidden="true" class="absolute inset-x-0 bottom-0.5 mx-auto h-1 w-6 rounded-full bg-fg-muted"></span>
                                                    <span x-text="endLabel(shape)" class="absolute -bottom-3 right-1 rounded-sm bg-fg px-1.5 py-0.5 text-xs font-medium tabular-nums text-canvas"></span>
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
                            <div data-atom-calendar-line x-bind:class="hour === 0 ? '' : 'border-t'" class="border-border">
                                <div data-atom-calendar-half class="border-t border-dashed border-border/50"></div>
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
        class="w-64 rounded-panel border border-border-strong bg-surface-raised p-0 text-fg shadow-pop outline-hidden">
            <div class="flex items-start justify-between gap-2 p-3 pb-2">
                <div>
                    <div x-text="popoverDate ? weekdayName(popoverDate) : ''" class="text-xs font-medium uppercase tracking-wide text-fg-muted"></div>
                    <div
                    x-text="popoverDate ? Number(popoverDate.slice(8, 10)) : ''"
                    x-bind:class="popoverDate && popoverDate === todayDate ? 'bg-accent-ink text-surface' : 'text-fg'"
                    class="inline-flex size-9 items-center justify-center rounded-full text-2xl font-semibold tabular-nums"></div>
                </div>

                <atom:button variant="ghost" icon="close" :aria-label="t('Close')" x-on:click="closeDay()" />
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
