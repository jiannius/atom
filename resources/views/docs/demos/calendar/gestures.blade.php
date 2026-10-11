@php
$monday = \Jiannius\Atom\Services\Calendar::now()->startOfWeek();
$at = fn (int $day, int $hour, int $minute = 0) => $monday->copy()->addDays($day)->setTime($hour, $minute);

$items = [
    ['id' => 1, 'title' => 'Offsite', 'start' => $monday->copy()->addDays(1)->toDateString(), 'end' => $monday->copy()->addDays(2)->toDateString(), 'color' => 'purple'],
    ['id' => 2, 'title' => 'Design review', 'start' => $at(0, 10), 'end' => $at(0, 11, 30), 'color' => 'orange'],
    ['id' => 3, 'title' => 'Client call', 'start' => $at(2, 11), 'end' => $at(2, 12), 'color' => 'red'],
    ['id' => 4, 'title' => 'Locked item', 'start' => $at(3, 9), 'end' => $at(3, 10), 'draggable' => false, 'resizable' => false],
];
@endphp

{{-- The events bubble from the calendar, so one listener around it hears all four. detail.waitUntil is a function and is left out of the JSON. --}}
<div
x-data="{
    log: [],
    add (event) {
        this.log.unshift({ event: event.type, ...event.detail })
        this.log.splice(6)
    },
}"
x-on:calendar-select="add($event)"
x-on:calendar-item-move="add($event)"
x-on:calendar-item-resize="add($event)"
x-on:calendar-item-click="add($event)"
class="space-y-4">
    <atom:calendar selectable :views="['month', 'week', 'day']" view="week" :items="$items" />

    <pre x-text="log.length ? log.map((entry) => JSON.stringify(entry)).join('\n') : 'Move, resize, select or click something.'" data-calendar-log class="overflow-x-auto rounded-lg bg-zinc-100 p-3 text-xs text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200"></pre>
</div>
