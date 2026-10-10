<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Traits\AtomComponent;
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

    public int $renders = 0;

    public bool $extra = false;

    /**
     * Open at the date and in the view the page's query string names, for a test that needs to start in the grid.
     */
    public function mount(): void
    {
        $this->date = (string) request()->query('date', $this->date);
        $this->view = (string) request()->query('view', $this->view);
        $this->busy = request()->boolean('busy');
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
                // Wednesday 28 October: a 10 minute item, one with no end at the end of the day, and four all-day items (one over the lane limit)
                ['id' => 27, 'title' => 'Quick call', 'start' => $local('2026-10-28 11:00'), 'end' => $local('2026-10-28 11:10')],
                ['id' => 28, 'title' => 'Late call', 'start' => $local('2026-10-28 23:50')],
                ['id' => 29, 'title' => 'Public holiday', 'start' => '2026-10-28', 'color' => 'green'],
                ['id' => 31, 'title' => 'Training', 'start' => '2026-10-28', 'color' => 'blue'],
                ['id' => 32, 'title' => 'Audit', 'start' => '2026-10-28', 'color' => 'red'],
            ]);
        }

        if ($this->extra) {
            $items[] = ['id' => 99, 'title' => 'Added later', 'start' => '2026-10-09', 'color' => 'green'];
        }

        return collect($items)
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
