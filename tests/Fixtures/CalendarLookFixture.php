<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * The calendar as it looks, for the screenshots and the contrast check: a bound anchor date (so the toolbar is the whole one) and
 * one fixed set of items that covers every state the design names. Time is frozen at 2026-10-09 10:30 in the viewer's zone on
 * every request, so "today", the now line and what is past never move.
 */
class CalendarLookFixture extends Component
{
    use AtomComponent;

    public string $date = '2026-10-09';

    /** The view the calendar opens in; ?view=week. */
    public string $view = 'month';

    /**
     * Open at the date and in the view the page's query string names.
     */
    public function mount(): void
    {
        $this->date = (string) request()->query('date', $this->date);
        $this->view = (string) request()->query('view', $this->view);
    }

    /**
     * Freeze the clock for every request.
     */
    public function boot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:30:00', Calendar::timezone()));
    }

    /**
     * Every item. Protected: the view's closure is bound to the component.
     *
     * Covers: today (Friday 9 October), the weekend, days outside the month, past days, every colour and a hex one, the danger
     * style, an icon, a busy day that overflows ("+N more"), bars across a week and across the month's edge, an overlap of two
     * and of three items, an all-day row with more bars than lanes, an item with no end, a short one, and one past midnight.
     *
     * @return list<array<string, mixed>>
     */
    protected function calendarItems(Carbon $from, Carbon $to): array
    {
        $at = fn (string $moment) => Carbon::parse($moment, Calendar::timezone());

        return [
            // bars: the whole of a week, over a month's edge, across a week's end, a single day
            ['id' => 'launch', 'title' => 'Launch week', 'start' => '2026-10-05', 'end' => '2026-10-09', 'color' => 'purple'],
            ['id' => 'visit', 'title' => 'Site visit', 'start' => '2026-09-28', 'end' => '2026-10-02', 'color' => 'blue'],
            ['id' => 'conference', 'title' => 'Conference', 'start' => '2026-10-09', 'end' => '2026-10-14', 'color' => 'orange', 'icon' => 'calendar'],
            ['id' => 'holiday', 'title' => 'Public holiday', 'start' => '2026-10-10', 'color' => 'green'],
            ['id' => 'overdue', 'title' => 'Invoice overdue', 'start' => '2026-10-09', 'danger' => true],
            // more all-day items on Wednesday 7 October than the row has lanes for
            ['id' => 'training', 'title' => 'Training', 'start' => '2026-10-06', 'end' => '2026-10-07', 'color' => 'yellow'],
            ['id' => 'audit', 'title' => 'Audit', 'start' => '2026-10-07', 'color' => 'red'],
            ['id' => 'offsite', 'title' => 'Offsite', 'start' => '2026-10-07', 'color' => 'zinc'],
            ['id' => 'supplier', 'title' => 'Supplier day', 'start' => '2026-10-07', 'end' => '2026-10-08', 'color' => 'black'],

            // Friday 9 October, today: three overlapping items, a long one, one with no end, a short one, one past midnight
            ['id' => 'standup', 'title' => 'Team standup', 'start' => $at('2026-10-09 09:30'), 'end' => $at('2026-10-09 10:15'), 'color' => 'blue'],
            ['id' => 'design', 'title' => 'Design review', 'start' => $at('2026-10-09 10:00'), 'end' => $at('2026-10-09 11:30'), 'color' => 'orange'],
            ['id' => 'client', 'title' => 'Client call', 'start' => $at('2026-10-09 10:00'), 'end' => $at('2026-10-09 11:00'), 'color' => 'red'],
            ['id' => 'lunch', 'title' => 'Lunch with Sam', 'start' => $at('2026-10-09 12:30'), 'end' => $at('2026-10-09 13:30'), 'color' => 'yellow'],
            ['id' => 'bank', 'title' => 'Call the bank', 'start' => $at('2026-10-09 15:00'), 'color' => 'zinc'],
            ['id' => 'quick', 'title' => 'Quick check-in', 'start' => $at('2026-10-09 16:00'), 'end' => $at('2026-10-09 16:10'), 'icon' => 'calendar'],
            ['id' => 'night', 'title' => 'Night shift', 'start' => $at('2026-10-09 22:00'), 'end' => $at('2026-10-10 02:00'), 'color' => 'black'],
            ['id' => 'late', 'title' => 'Late call', 'start' => $at('2026-10-09 23:50')],

            // past days
            ['id' => 'monday', 'title' => 'Weekly sync', 'start' => $at('2026-10-05 09:00'), 'end' => $at('2026-10-05 09:30'), 'color' => 'blue'],
            ['id' => 'planning', 'title' => 'Planning', 'start' => $at('2026-10-06 11:00'), 'end' => $at('2026-10-06 12:00'), 'color' => 'green'],
            ['id' => 'review', 'title' => 'Review', 'start' => $at('2026-10-06 11:30'), 'end' => $at('2026-10-06 12:30'), 'color' => 'red'],
            ['id' => 'dentist', 'title' => 'Dentist', 'start' => $at('2026-10-08 08:00'), 'end' => $at('2026-10-08 09:00'), 'color' => 'zinc'],
            ['id' => 'hex', 'title' => 'Hex colour', 'start' => $at('2026-10-08 14:00'), 'end' => $at('2026-10-08 16:00'), 'color' => '#ff8800'],
            // hex colours a host might pick: a navy and a pale yellow, which a ground in one theme or the other swallows
            ['id' => 'hex-navy', 'title' => 'Navy hex', 'start' => $at('2026-10-08 11:00'), 'end' => $at('2026-10-08 12:00'), 'color' => '#000080'],
            ['id' => 'hex-pale', 'title' => 'Pale hex', 'start' => $at('2026-10-06 14:00'), 'end' => $at('2026-10-06 15:00'), 'color' => '#ffee00'],
            ['id' => 'hex-bar', 'title' => 'Pale hex bar', 'start' => '2026-10-16', 'color' => '#ffee00'],
            ['id' => 'moved', 'title' => 'Item to move', 'start' => $at('2026-10-08 17:00'), 'end' => $at('2026-10-08 18:00'), 'color' => 'purple'],

            // Wednesday 7 October: the three side by side in a week column, as long as a workshop title
            ['id' => 'workshop-a', 'title' => 'Workshop A', 'start' => $at('2026-10-07 09:00'), 'end' => $at('2026-10-07 10:30'), 'color' => 'blue'],
            ['id' => 'workshop-b', 'title' => 'Workshop B', 'start' => $at('2026-10-07 09:00'), 'end' => $at('2026-10-07 10:00'), 'color' => 'green'],
            ['id' => 'workshop-c', 'title' => 'Workshop C', 'start' => $at('2026-10-07 09:30'), 'end' => $at('2026-10-07 11:00'), 'color' => 'purple'],

            // a busy day that overflows in the month: Wednesday 14 October
            ['id' => 'busy-1', 'title' => 'Breakfast meeting', 'start' => $at('2026-10-14 08:00'), 'end' => $at('2026-10-14 09:00'), 'color' => 'red'],
            ['id' => 'busy-2', 'title' => 'Design crit', 'start' => $at('2026-10-14 10:00'), 'end' => $at('2026-10-14 11:00'), 'color' => 'orange'],
            ['id' => 'busy-3', 'title' => 'Lunch and learn', 'start' => $at('2026-10-14 12:30'), 'end' => $at('2026-10-14 13:30'), 'color' => 'yellow'],
            ['id' => 'busy-4', 'title' => 'Call the supplier', 'start' => $at('2026-10-14 15:00'), 'color' => 'zinc'],
            ['id' => 'busy-5', 'title' => 'School pickup', 'start' => $at('2026-10-14 17:15'), 'end' => $at('2026-10-14 17:45'), 'color' => 'black'],
            ['id' => 'busy-6', 'title' => 'Open the docs', 'start' => '2026-10-14', 'href' => '/atom/docs', 'icon' => 'calendar'],

            // later in the month, and the days outside it
            ['id' => 'later', 'title' => 'Quarter planning', 'start' => $at('2026-10-20 14:00'), 'end' => $at('2026-10-20 15:00'), 'color' => 'green'],
            ['id' => 'tail', 'title' => 'Month end review', 'start' => '2026-10-30', 'color' => 'purple'],
            ['id' => 'november', 'title' => 'November kick-off', 'start' => $at('2026-11-02 09:00'), 'end' => $at('2026-11-02 10:00'), 'color' => 'red'],
            ['id' => 'september', 'title' => 'September tail', 'start' => $at('2026-09-29 13:00'), 'end' => $at('2026-09-29 14:00'), 'color' => 'blue'],
        ];
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::calendar-look');
    }
}
