<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * A Livewire host for <atom:calendar>: a bound anchor date, a fixed set of items in October and
 * November 2026, and two buttons that re-render the component, one of which changes the data.
 *
 * Time starts at 2026-10-09 10:30 (the viewer's own clock) on every request (boot() runs on each Livewire update), and each render
 * is one second later per bump, so "today" is stable and the clock is not. Items are built in the viewer's own zone, so their wall clock is what is written here.
 */
class CalendarFixture extends Component
{
    use AtomComponent;

    public string $date = '2026-10-09';

    /** The view the calendar opens in; a page asks for one with ?view=week. */
    public string $view = 'month';

    /** Whether the week of 26 October is crowded, for the week and day views; ?busy=1. Off, the month is the one the month view's tests count. */
    public bool $busy = false;

    /** Whether the calendar creates on a click or a drag; ?selectable=1. */
    public bool $selectable = false;

    /** The first day of the week; ?weekStart=sunday. */
    public string $weekStart = 'monday';

    /** Whether items can be dragged and pulled by their end; ?draggable=0 turns both off. */
    public bool $draggable = true;

    /** The time grid's slot length in minutes; ?slot=15. */
    public int $slot = 30;

    /** Whether the month has an item that may be neither moved nor resized (an item's own flags beat the calendar's); ?locked=1. */
    public bool $locked = false;

    /** Where the items that were moved or resized were put, as the host stored them: [id => { start, end }], in the viewer's wall clock. */
    public array $placed = [];

    /** What reschedule() was given, one row each, for a test to read off the page. */
    public array $rescheduled = [];

    /** Ids of items the host has taken away, for a test that removes the item that is being dragged. */
    public array $hidden = [];

    /** What create() was given and what it made of it, one row each, for a test to read off the page. */
    public array $created = [];

    public int $renders = 0;

    public bool $extra = false;

    /** One item on 16 October, a day with none otherwise: a day the list can be opened on and then emptied. */
    public bool $lone = false;

    /** Four items on 17 October, over the month's limit of three, so that day has a "+N more" that goes when they do. */
    public bool $crowd = false;

    /**
     * Open at the date and in the view the page's query string names, for a test that needs to start in the grid.
     */
    public function mount(): void
    {
        $this->date = (string) request()->query('date', $this->date);
        $this->view = (string) request()->query('view', $this->view);
        $this->busy = request()->boolean('busy');
        $this->selectable = request()->boolean('selectable');
        $this->weekStart = (string) request()->query('weekStart', $this->weekStart);
        $this->draggable = request()->query('draggable', '1') !== '0';
        $this->slot = (int) request()->query('slot', $this->slot);
        $this->locked = request()->boolean('locked');
    }

    /**
     * Freeze the clock for every request.
     */
    public function boot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:30:00', Calendar::timezone()));
    }

    /**
     * Re-render the component without touching the data: Livewire morphs the markup around the calendar's wire:ignore'd body.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Re-render with one more item, so the payload on the root changes.
     */
    public function toggleExtra(): void
    {
        $this->extra = ! $this->extra;
    }

    /**
     * Make an item of what a selection says, as a host does: the calendar's wall-clock strings go straight to Calendar::fromLocal(), which gives
     * the instant in the app's timezone, and what is stored is that instant. The row is kept as the host got it and as it was stored, for the test.
     */
    public function create(string $start, string $end, bool $allDay): void
    {
        $this->created[] = [
            'id' => 'created-'.(count($this->created) + 1),
            'start' => $start,
            'end' => $end,
            'allDay' => $allDay,
            'startUtc' => Calendar::fromLocal($start)->utc()->format('Y-m-d\TH:i:s\Z'),
            'endUtc' => Calendar::fromLocal($end)->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Put an item where a move or a resize says, as a host does: the calendar's wall-clock strings are what is stored (an instant, through
     * Calendar::fromLocal(), for a time), and the next render has the item there.
     */
    public function reschedule(int|string $id, string $start, ?string $end): void
    {
        $this->place($id, $start, $end);
    }

    /**
     * The same, answering with no render: the calendar has no payload to take the item's place from, and keeps it where it was put.
     */
    #[Renderless]
    public function rescheduleQuiet(int|string $id, string $start, ?string $end): void
    {
        $this->place($id, $start, $end);
    }

    /**
     * Take an item away, as another user deleting it would.
     */
    public function hide(int|string $id): void
    {
        $this->hidden[] = $id;
    }

    /**
     * Store where an item was put, and what the host was given.
     */
    protected function place(int|string $id, string $start, ?string $end): void
    {
        $this->placed[(string) $id] = ['start' => $start, 'end' => $end];
        $this->rescheduled[] = [
            'id' => $id,
            'start' => $start,
            'end' => $end,
            'startUtc' => str_contains($start, 'T') ? Calendar::fromLocal($start)->utc()->format('Y-m-d\TH:i:s\Z') : null,
        ];
    }

    /**
     * Add or take away the one item of 16 October.
     */
    public function toggleLone(): void
    {
        $this->lone = ! $this->lone;
    }

    /**
     * Add or take away the four items of 17 October.
     */
    public function toggleCrowd(): void
    {
        $this->crowd = ! $this->crowd;
    }

    /**
     * Every item that overlaps the window the calendar asked for. Protected: the view's closure is bound to the component.
     *
     * @return list<array<string, mixed>>
     */
    protected function calendarItems(Carbon $from, Carbon $to): array
    {
        $local = fn (string $moment) => Carbon::parse($moment, Calendar::timezone());

        $items = [
            ['id' => 1, 'title' => 'Team standup', 'start' => $local('2026-10-09 09:30'), 'end' => $local('2026-10-09 10:00'), 'color' => 'blue'],
            ['id' => 2, 'title' => 'Launch week', 'start' => '2026-10-05', 'end' => '2026-10-09', 'color' => 'purple'],
            ['id' => 3, 'title' => 'Conference', 'start' => '2026-10-02', 'end' => '2026-10-06', 'color' => 'green'],
            ['id' => 4, 'title' => 'Invoice overdue', 'start' => '2026-10-09', 'danger' => true],
            ['id' => 5, 'title' => 'Dentist', 'start' => $local('2026-10-14 08:00'), 'end' => $local('2026-10-14 09:00'), 'color' => 'red'],
            ['id' => 6, 'title' => 'Design review', 'start' => $local('2026-10-14 10:00'), 'end' => $local('2026-10-14 11:00'), 'color' => 'orange'],
            ['id' => 7, 'title' => 'Lunch with Sam', 'start' => $local('2026-10-14 12:30'), 'end' => $local('2026-10-14 13:30'), 'color' => 'yellow'],
            ['id' => 8, 'title' => 'Call the bank', 'start' => $local('2026-10-14 15:00'), 'color' => 'zinc'],
            ['id' => 9, 'title' => 'School pickup', 'start' => $local('2026-10-14 17:15'), 'end' => $local('2026-10-14 17:45'), 'color' => 'black'],
            ['id' => 10, 'title' => 'Hex colour', 'start' => $local('2026-10-20 14:00'), 'end' => $local('2026-10-20 15:00'), 'color' => '#ff8800'],
            ['id' => 11, 'title' => 'Open the docs', 'start' => '2026-10-21', 'href' => '/atom/docs', 'icon' => 'calendar'],
            ['id' => 12, 'title' => 'Night shift', 'start' => $local('2026-10-22 22:00'), 'end' => $local('2026-10-23 02:00')],
            ['id' => 13, 'title' => 'Unsafe link', 'start' => '2026-10-23', 'href' => 'javascript:window.__hacked = 1'],
            ['id' => 14, 'title' => 'Month end review', 'start' => '2026-11-20'],
            ['id' => 15, 'title' => 'November kick-off', 'start' => $local('2026-11-02 09:00'), 'end' => $local('2026-11-02 10:00'), 'color' => 'red'],
            ['id' => 16, 'title' => 'Septembers tail', 'start' => '2026-09-29'],
            // November's window uses no icon but the warning, so the set of icon templates changes between windows
            ['id' => 17, 'title' => 'Contract overdue', 'start' => '2026-11-12', 'danger' => true],
        ];

        if ($this->busy) {
            $items = array_merge($items, [
                // Tuesday 27 October: a chain of overlaps (2 lanes), a trio (3 lanes) and a lone item; the all-day row has a bar over three days
                ['id' => 20, 'title' => 'Planning', 'start' => $local('2026-10-27 09:00'), 'end' => $local('2026-10-27 10:00'), 'color' => 'blue'],
                ['id' => 21, 'title' => 'Review', 'start' => $local('2026-10-27 09:30'), 'end' => $local('2026-10-27 10:30'), 'color' => 'purple'],
                ['id' => 22, 'title' => 'Retro', 'start' => $local('2026-10-27 10:00'), 'end' => $local('2026-10-27 11:00'), 'color' => 'green'],
                ['id' => 23, 'title' => 'Solo', 'start' => $local('2026-10-27 13:00'), 'end' => $local('2026-10-27 14:00'), 'color' => 'orange'],
                ['id' => 24, 'title' => 'Alpha', 'start' => $local('2026-10-27 15:00'), 'end' => $local('2026-10-27 16:00'), 'color' => 'red'],
                ['id' => 25, 'title' => 'Beta', 'start' => $local('2026-10-27 15:00'), 'end' => $local('2026-10-27 16:00'), 'color' => 'yellow'],
                ['id' => 26, 'title' => 'Gamma', 'start' => $local('2026-10-27 15:30'), 'end' => $local('2026-10-27 16:30'), 'color' => 'zinc'],
                ['id' => 30, 'title' => 'Offsite', 'start' => '2026-10-27', 'end' => '2026-10-29', 'color' => 'purple'],
                // Wednesday 28 October: a 10 minute item, one with no end at the end of the day (and one before it, which the first is drawn over unless the lanes count on what is drawn), and four all-day items (one over the lane limit)
                ['id' => 27, 'title' => 'Quick call', 'start' => $local('2026-10-28 11:00'), 'end' => $local('2026-10-28 11:10')],
                ['id' => 28, 'title' => 'Late call', 'start' => $local('2026-10-28 23:50')],
                ['id' => 33, 'title' => 'Evening wrap', 'start' => $local('2026-10-28 23:00'), 'end' => $local('2026-10-28 23:45')],
                ['id' => 29, 'title' => 'Public holiday', 'start' => '2026-10-28', 'color' => 'green'],
                ['id' => 31, 'title' => 'Training', 'start' => '2026-10-28', 'color' => 'blue'],
                ['id' => 32, 'title' => 'Audit', 'start' => '2026-10-28', 'color' => 'red'],
            ]);
        }

        if ($this->locked) {
            $items[] = ['id' => 80, 'title' => 'Locked item', 'start' => '2026-10-13', 'end' => '2026-10-14', 'draggable' => false, 'resizable' => false];
            $items[] = ['id' => 81, 'title' => 'Fixed length', 'start' => '2026-10-15', 'resizable' => false];
        }

        if ($this->lone) {
            $items[] = ['id' => 90, 'title' => 'Lone item', 'start' => '2026-10-16'];
        }

        if ($this->crowd) {
            foreach (range(1, 4) as $number) {
                $items[] = ['id' => 90 + $number, 'title' => 'Crowd '.$number, 'start' => '2026-10-17'];
            }
        }

        if ($this->extra) {
            $items[] = ['id' => 99, 'title' => 'Added later', 'start' => '2026-10-09', 'color' => 'green'];
        }

        foreach ($this->created as $row) {
            // what was stored is an instant; an all-day item is the date, the last day included
            $items[] = $row['allDay']
                ? ['id' => $row['id'], 'title' => 'Created', 'start' => $row['start'], 'end' => $row['end'], 'all_day' => true, 'color' => 'blue']
                : ['id' => $row['id'], 'title' => 'Created', 'start' => Calendar::fromLocal($row['start']), 'end' => Calendar::fromLocal($row['end']), 'color' => 'blue'];
        }

        // where the host put the items that were moved or resized: a date for an all-day item, an instant for a timed one
        $items = array_map(function (array $item) {
            $row = $this->placed[(string) $item['id']] ?? null;

            if (! $row) {
                return $item;
            }

            if (! str_contains($row['start'], 'T')) {
                return [...$item, 'start' => $row['start'], 'end' => $row['end'], 'all_day' => true];
            }

            return [...$item, 'start' => Calendar::fromLocal($row['start']), 'end' => $row['end'] ? Calendar::fromLocal($row['end']) : null, 'all_day' => false];
        }, $items);

        return collect($items)
            ->reject(fn (array $item) => in_array($item['id'], $this->hidden, true))
            ->filter(function (array $item) use ($from, $to) {
                $start = $item['start'] instanceof Carbon ? $item['start'] : Carbon::parse($item['start'], Calendar::appTimezone());

                return $start->between($from, $to);
            })
            ->values()
            ->all();
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        // the clock is not frozen between renders in real life: each one is a second later than the last
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:30:00', Calendar::timezone())->addSeconds($this->renders));

        return view('atom-test::calendar');
    }
}
