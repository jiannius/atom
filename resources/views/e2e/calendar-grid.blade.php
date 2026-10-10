<atom:html title="E2E: Calendar (time grid)" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- A static calendar for the week and day views, in a viewer's timezone and at a moment the query string names:
     ?tz=America/Los_Angeles&now=2026-03-08T11:30:00Z&date=2026-03-08&view=week&scenario=dst-spring. The route
     (tests/Fixtures/E2EServiceProvider.php) signs the viewer in and freezes the clock, as the items below are
     instants and the calendar turns them into the viewer's wall clock. --}}
<style>
    *, ::before, ::after { box-sizing: border-box }
    .flex { display: flex }
    .flex-col { flex-direction: column }
    .inline-flex { display: inline-flex }
    .items-center { align-items: center }
    .items-start { align-items: flex-start }
    .justify-between { justify-content: space-between }
    .justify-center { justify-content: center }
    .flex-wrap { flex-wrap: wrap }
    .shrink-0 { flex-shrink: 0 }
    .min-w-0 { min-width: 0 }
    .w-full { width: 100% }
    .h-full { height: 100% }
    .w-64 { width: 16rem }
    .h-5 { height: 1.25rem }
    .h-6 { height: 1.5rem }
    .size-1\.5 { width: .375rem; height: .375rem }
    .size-2 { width: .5rem; height: .5rem }
    .size-3 { width: .75rem; height: .75rem }
    .size-4 { width: 1rem; height: 1rem }
    .size-5 { width: 1.25rem; height: 1.25rem }
    .size-6 { width: 1.5rem; height: 1.5rem }
    .size-8 { width: 2rem; height: 2rem }
    .size-9 { width: 2.25rem; height: 2.25rem }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
    .overflow-hidden { overflow: hidden }
    .gap-0\.5 { gap: .125rem }
    .gap-1 { gap: .25rem }
    .gap-1\.5 { gap: .375rem }
    .gap-3 { gap: .75rem }
    .p-3 { padding: .75rem }
    .p-4 { padding: 1rem }
    .px-1 { padding-inline: .25rem }
    .px-1\.5 { padding-inline: .375rem }
    .py-0\.5 { padding-block: .125rem }
    .py-2 { padding-block: .5rem }
    .text-xs { font-size: .75rem }
    .text-left { text-align: left }
    .text-right { text-align: right }
    .rounded-full { border-radius: 9999px }
    .cursor-pointer { cursor: pointer }
    .relative { position: relative }
    [data-atom-icon] svg { width: 100%; height: 100% }
</style>
@php
    $items = match (request('scenario')) {
        // 2026-03-08: the clocks in Los Angeles go from 02:00 to 03:00. 01:30 to 03:30 on the wall clock is one real hour.
        'dst-spring' => [
            ['id' => 'across', 'title' => 'Across the gap', 'start' => '2026-03-08T01:30:00-08:00', 'end' => '2026-03-08T03:30:00-07:00'],
            ['id' => 'after', 'title' => 'After the gap', 'start' => '2026-03-08T03:00:00-07:00', 'end' => '2026-03-08T04:00:00-07:00'],
            ['id' => 'morning', 'title' => 'Morning call', 'start' => '2026-03-08T09:00:00-07:00', 'end' => '2026-03-08T10:00:00-07:00'],
        ],
        // 2026-11-01: 01:00 to 02:00 happens twice (PDT, then PST).
        'dst-fall' => [
            ['id' => 'first', 'title' => 'First pass', 'start' => '2026-11-01T01:30:00-07:00', 'end' => '2026-11-01T01:55:00-07:00'],
            ['id' => 'second', 'title' => 'Second pass', 'start' => '2026-11-01T01:30:00-08:00', 'end' => '2026-11-01T02:00:00-08:00'],
            ['id' => 'hour', 'title' => 'The repeated hour', 'start' => '2026-11-01T01:30:00-07:00', 'end' => '2026-11-01T01:30:00-08:00'],
            ['id' => 'noon', 'title' => 'Noon', 'start' => '2026-11-01T12:00:00-08:00', 'end' => '2026-11-01T13:00:00-08:00'],
        ],
        // the look: overlaps, an all-day row, an item with no end, a short one, and today's now line
        default => [
            ['id' => 'launch', 'title' => 'Launch week', 'start' => '2026-10-05', 'end' => '2026-10-09', 'color' => 'purple'],
            ['id' => 'overdue', 'title' => 'Invoice overdue', 'start' => '2026-10-09', 'danger' => true],
            ['id' => 'holiday', 'title' => 'Public holiday', 'start' => '2026-10-10', 'color' => 'green'],
            ['id' => 'standup', 'title' => 'Team standup', 'start' => '2026-10-09T09:30:00+08:00', 'end' => '2026-10-09T10:15:00+08:00', 'color' => 'blue'],
            ['id' => 'design', 'title' => 'Design review', 'start' => '2026-10-09T10:00:00+08:00', 'end' => '2026-10-09T11:30:00+08:00', 'color' => 'orange'],
            ['id' => 'client', 'title' => 'Client call', 'start' => '2026-10-09T10:00:00+08:00', 'end' => '2026-10-09T11:00:00+08:00', 'color' => 'red'],
            ['id' => 'lunch', 'title' => 'Lunch with Sam', 'start' => '2026-10-09T12:30:00+08:00', 'end' => '2026-10-09T13:30:00+08:00', 'color' => 'yellow'],
            ['id' => 'bank', 'title' => 'Call the bank', 'start' => '2026-10-09T15:00:00+08:00', 'color' => 'zinc'],
            ['id' => 'quick', 'title' => 'Quick check-in', 'start' => '2026-10-09T16:00:00+08:00', 'end' => '2026-10-09T16:10:00+08:00', 'icon' => 'calendar'],
            ['id' => 'late', 'title' => 'Late call', 'start' => '2026-10-09T23:50:00+08:00'],
            ['id' => 'night', 'title' => 'Night shift', 'start' => '2026-10-09T22:00:00+08:00', 'end' => '2026-10-10T02:00:00+08:00', 'color' => 'black'],
            ['id' => 'hex', 'title' => 'Hex colour', 'start' => '2026-10-07T14:00:00+08:00', 'end' => '2026-10-07T16:00:00+08:00', 'color' => '#ff8800'],
            ['id' => 'trio-a', 'title' => 'Workshop A', 'start' => '2026-10-07T09:00:00+08:00', 'end' => '2026-10-07T10:30:00+08:00', 'color' => 'blue'],
            ['id' => 'trio-b', 'title' => 'Workshop B', 'start' => '2026-10-07T09:00:00+08:00', 'end' => '2026-10-07T10:00:00+08:00', 'color' => 'green'],
            ['id' => 'trio-c', 'title' => 'Workshop C', 'start' => '2026-10-07T09:30:00+08:00', 'end' => '2026-10-07T11:00:00+08:00', 'color' => 'purple'],
        ],
    };
@endphp
<div class="p-4" x-data="{ clicked: [] }" x-on:calendar-item-click="clicked.push($event.detail.id)">
    <div>Clicked: <span data-clicked x-text="clicked.join(',')"></span></div>

    <atom:calendar
    :month="request('date', '2026-10-09')"
    :views="['month', 'week', 'day']"
    :view="request('view', 'week')"
    :scroll-time="request('scroll', '06:00')"
    :now-indicator="request('indicator', '1') !== '0'"
    :items="$items"
    data-probe="grid" />
</div>

@livewireScripts
</atom:html>
