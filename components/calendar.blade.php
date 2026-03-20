@props([
    'name' => null,
    'modes' => ['calendar', 'timeline'],
    'periods' => ['month', 'week', 'day'],
])

@php
$modes = (array) $modes;
$periods = (array) $periods;
@endphp

<link rel="stylesheet" href="{{ app('atom')->asset()->version('calendar.css') }}">

<div
x-data="calendar({
    name: @js($name),
    mode: @js(head($modes)),
    period: @js(head($periods)),
})"
x-modelable="date"
x-on:add-calendar-events.window="addEvents($event.detail)"
x-on:update-calendar-events.window="updateEvents($event.detail)"
x-on:remove-calendar-events.window="removeEvents($event.detail)"
data-atom-calendar
{{ $attributes->class([
    'group/calendar',
    '[&_.ec-sidebar]:rounded-l-lg',
    '[&_.ec-header]:rounded-t-lg [&_.ec-header]:bg-white dark:[&_.ec-header]:bg-zinc-800',
    '[&_.ec-body]:rounded-b-lg [&_.ec-body]:bg-white dark:[&_.ec-body]:bg-zinc-800',

    '[&_.ec-event]:h-full',
    '[&_.ec-event]:bg-red-800',
    '[&_.ec-event]:rounded-none',
    '[&_.ec-timeline_.ec-events]:h-[110px]',
]) }}>
    <div class="flex flex-wrap items-center gap-3">
        <div class="grow flex items-center gap-3">
            <div @class([
                'shrink-0 flex items-center divide-x dark:divide-zinc-700 border dark:border-zinc-700 rounded-md shadow-sm overflow-hidden bg-white dark:bg-zinc-800',
                '[&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:gap-2 [&_button]:size-8 [&_button]:hover:bg-zinc-100 [&_button]:dark:hover:bg-zinc-700',
            ])>
                <atom:tooltip content="Today">
                    <button type="button" x-on:click="today()">
                        <atom:icon.location class="size-4" />
                    </button>
                </atom:tooltip>

                <button type="button" x-on:click="prev()">
                    <atom:icon.left class="size-4" />
                </button>

                <button type="button" x-on:click="next()">
                    <atom:icon.right class="size-4" />
                </button>
            </div>

            <div class="shrink-0">
                <atom:date-picker x-model="date">
                    <button type="button" class="flex items-center gap-2">
                        <div class="text-xl">
                            <span x-text="day" class="font-thin"></span>
                            <span x-text="month" class="font-thin"></span>
                            <span x-text="year" class="font-bold"></span>
                        </div>

                        <atom:icon.dropdown />
                    </button>
                </atom:date-picker>
            </div>

            <div class="shrink-0 flex items-center justify-center opacity-0 group-[.is-loading]/calendar:opacity-100 transition-opacity duration-300 delay-500">
                <atom:icon.loading />
            </div>
        </div>

        <div @class([
            'shrink-0 flex flex-wrap items-center gap-3',
            '[&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:gap-2 [&_button]:size-8 [&_button]:hover:bg-zinc-100 [&_button]:dark:hover:bg-zinc-700',
        ])>
            @if (count($periods) > 1)
                <div class="shrink-0 flex items-center divide-x dark:divide-zinc-700 border dark:border-zinc-700 rounded-md shadow-sm overflow-hidden bg-white dark:bg-zinc-800">
                    @foreach ($periods as $period)
                        <atom:tooltip :content="str()->title($period)">
                            <button
                            type="button"
                            x-on:click="period = @js($period)"
                            x-bind:class="period === @js($period) ? 'bg-zinc-100 dark:bg-zinc-700' : ''">
                                <x-dynamic-component :component="match ($period) {
                                    'month' => 'atom::icon.calendar',
                                    'week' => 'atom::icon.columns',
                                    'day' => 'atom::icon.queue-list',
                                }" class="size-4" />
                            </button>
                        </atom:tooltip>
                    @endforeach
                </div>
            @endif

            @if (count($modes) > 1)
                <div class="shrink-0 flex items-center divide-x dark:divide-zinc-700 border dark:border-zinc-700 rounded-md shadow-sm overflow-hidden bg-white dark:bg-zinc-800">
                    @foreach ($modes as $mode)
                        <atom:tooltip :content="str()->title($mode).' Mode'">
                            <button
                            type="button"
                            x-on:click="mode = @js($mode)"
                            x-bind:class="mode === @js($mode) ? 'bg-zinc-100 dark:bg-zinc-700' : ''">
                                <x-dynamic-component :component="match ($mode) {
                                    'calendar' => 'atom::icon.calendar-days',
                                    'timeline' => 'atom::icon.gantt-chart',
                                }" class="size-4" />
                            </button>
                        </atom:tooltip>
                    @endforeach
                </div>
            @endif

            @if ($slot->isNotEmpty())
                <div class="shrink-0">
                    {{ $slot }}
                </div>
            @endif
        </div>
    </div>

    <div x-ref="calendar"></div>
</div>