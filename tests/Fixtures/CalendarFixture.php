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
 * Time is frozen at 2026-10-09 10:30 on every request (boot() runs on each Livewire update), so
 * "today" is stable. Items are built in the viewer's own zone, so their wall clock is what is written here.
 */
class CalendarFixture extends Component
{
    use AtomComponent;

    public string $date = '2026-10-09';

    public int $renders = 0;

    public bool $extra = false;

    /**
     * Freeze the clock for every request.
     */
    public function boot(): void
    {
        Carbon::setTestNow('2026-10-09 10:30:00');
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
        return view('atom-test::calendar');
    }
}
