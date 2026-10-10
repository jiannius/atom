<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\CalendarItem;
use Jiannius\Atom\Services\Carbon;

enum CalendarTestStatus: string
{
    case DONE = 'done';

    public function color(): string
    {
        return 'green';
    }
}

/**
 * Sign in a viewer with a timezone, which is where Carbon::getLocalTimezone() reads it from.
 * config('app.timezone') stays UTC, the way a host that stores instants in UTC has it.
 */
function calendarTestViewer(string $timezone): void
{
    Auth::setUser(new GenericUser(['id' => 1, 'timezone' => $timezone]));
}

/**
 * The one normalised item for a host item.
 *
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function calendarTestItem(array $item): array
{
    $items = Calendar::make()->items([$item + ['id' => 'a', 'title' => 'Item']]);

    expect($items)->toHaveCount(1);

    return $items[0];
}

afterEach(function () {
    Carbon::setTestNow();
});

describe('calendar window', function () {
    it('starts the month grid on Monday by default', function () {
        $window = Calendar::make()->window('2026-10-09');

        expect($window['start'])->toBe('2026-09-28')
            ->and($window['days'])->toHaveCount(42)
            ->and($window['end'])->toBe('2026-11-08')
            ->and($window['days'][0]['weekday'])->toBe('monday');
    });

    it('starts the month grid on Sunday when asked', function () {
        $window = Calendar::make(['week_start' => 'sunday'])->window('2026-10-09');

        expect($window['start'])->toBe('2026-09-27')
            ->and($window['end'])->toBe('2026-11-07')
            ->and($window['days'][0]['weekday'])->toBe('sunday');
    });

    it('follows any week start, and does not step back a week when the month already starts on it', function (string $weekStart, string $first) {
        // 1 February 2026 is a Sunday
        expect(Calendar::make(['week_start' => $weekStart])->window('2026-02')['start'])->toBe($first);
    })->with([
        'monday' => ['monday', '2026-01-26'],
        'sunday' => ['sunday', '2026-02-01'],
        'saturday' => ['saturday', '2026-01-31'],
        'wednesday' => ['wednesday', '2026-01-28'],
    ]);

    it('falls back to Monday for a week start it does not know', function () {
        expect(Calendar::make(['week_start' => 'someday'])->window('2026-10-09')['start'])->toBe('2026-09-28');
    });

    it('hands the items query app-timezone instants for local midnight of the first day', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        $window = Calendar::make()->window('2026-10-09');

        expect($window['from']->toIso8601ZuluString())->toBe('2026-09-27T16:00:00Z')
            ->and($window['to']->toIso8601ZuluString())->toBe('2026-11-08T15:59:59Z');
    });

    it('computes the window in the viewer timezone, not the app timezone', function (string $viewer, string $from) {
        calendarTestViewer($viewer);

        expect(Calendar::make()->window('2026-10-09')['from']->toIso8601ZuluString())->toBe($from);
    })->with([
        'ahead of UTC' => ['Asia/Kuala_Lumpur', '2026-09-27T16:00:00Z'],
        'behind UTC' => ['America/Los_Angeles', '2026-09-28T07:00:00Z'],
        'UTC' => ['UTC', '2026-09-28T00:00:00Z'],
    ]);

    it('returns the window edges in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarTestViewer('Asia/Kuala_Lumpur');

        $from = Calendar::make()->window('2026-10-09')['from'];

        expect($from->getTimezone()->getName())->toBe('America/New_York')
            ->and($from->toIso8601ZuluString())->toBe('2026-09-27T16:00:00Z');
    });

    it('is the same 42 days whichever day of the month is the anchor', function () {
        $calendar = Calendar::make();

        expect($calendar->window('2026-10-01')['days'])->toBe($calendar->window('2026-10-31')['days'])
            ->and($calendar->window('2026-10')['days'])->toBe($calendar->window('2026-10-17')['days']);
    });

    it('steps over a daylight-saving change without losing or repeating a day', function () {
        calendarTestViewer('America/Los_Angeles');

        $dates = collect(Calendar::make()->window('2026-11-15')['days'])->pluck('date');

        expect($dates->unique())->toHaveCount(42)
            ->and($dates->contains('2026-11-01'))->toBeTrue()
            ->and($dates->contains('2026-11-02'))->toBeTrue();
    });

    it('hands the items query the right instants across a daylight-saving change', function () {
        calendarTestViewer('America/Los_Angeles');

        $window = Calendar::make()->window('2026-11-15');

        // PDT (UTC-7) on 26 October, PST (UTC-8) on 6 December: clocks went back on 1 November
        expect($window['start'])->toBe('2026-10-26')
            ->and($window['end'])->toBe('2026-12-06')
            ->and($window['from']->toIso8601ZuluString())->toBe('2026-10-26T07:00:00Z')
            ->and($window['to']->toIso8601ZuluString())->toBe('2026-12-07T07:59:59Z');
    });

    it('hands the items query the right instants when the clocks go forward inside the window', function () {
        calendarTestViewer('America/Los_Angeles');

        $window = Calendar::make()->window('2026-03-15');

        // 23 February is PST (UTC-8), 5 April is PDT (UTC-7): clocks went forward on 8 March
        expect($window['start'])->toBe('2026-02-23')
            ->and($window['end'])->toBe('2026-04-05')
            ->and($window['from']->toIso8601ZuluString())->toBe('2026-02-23T08:00:00Z')
            ->and($window['to']->toIso8601ZuluString())->toBe('2026-04-06T06:59:59Z');
    });

    it('ends the window on the last microsecond of its last day', function () {
        calendarTestViewer('UTC');

        expect(Calendar::make()->window('2026-10-09')['to']->format('H:i:s.u'))->toBe('23:59:59.999999');
    });

    it('flags the days outside the anchor month from the first day of the week', function (string $weekStart, int $before, int $after) {
        // 1 November 2026 is a Sunday: a Sunday start has no days before it, a Monday start has six
        $days = collect(Calendar::make(['week_start' => $weekStart])->window('2026-11-15')['days']);
        $outside = $days->pluck('outside');

        expect($days->first()['weekday'])->toBe($weekStart)
            ->and($outside->take($before)->every(fn ($flag) => $flag))->toBeTrue()
            ->and($outside->slice($before, 30)->every(fn ($flag) => ! $flag))->toBeTrue()
            ->and($outside->slice(-$after)->every(fn ($flag) => $flag))->toBeTrue()
            ->and($outside->filter()->count())->toBe($before + $after);
    })->with([
        'monday' => ['monday', 6, 6],
        'sunday' => ['sunday', 0, 12],
    ]);

    it('flags the weekend and the days outside the anchor month', function () {
        $days = collect(Calendar::make()->window('2026-10-09')['days'])->keyBy('date');

        expect($days['2026-10-03']['weekend'])->toBeTrue()
            ->and($days['2026-10-04']['weekend'])->toBeTrue()
            ->and($days['2026-10-05']['weekend'])->toBeFalse()
            ->and($days['2026-09-28']['outside'])->toBeTrue()
            ->and($days['2026-10-01']['outside'])->toBeFalse()
            ->and($days['2026-11-01']['outside'])->toBeTrue();
    });

    it('lets a host choose its own weekend', function () {
        $days = collect(Calendar::make(['weekend' => ['friday', 'saturday']])->window('2026-10-09')['days'])->keyBy('date');

        expect($days['2026-10-09']['weekend'])->toBeTrue()
            ->and($days['2026-10-10']['weekend'])->toBeTrue()
            ->and($days['2026-10-11']['weekend'])->toBeFalse();
    });

    it('accepts an empty weekend', function () {
        expect(collect(Calendar::make(['weekend' => []])->window('2026-10')['days'])->where('weekend', true))->toHaveCount(0);
    });
});

describe('calendar anchor', function () {
    it('reads Y-m-d and Y-m', function () {
        $calendar = Calendar::make();

        expect($calendar->anchor('2026-10-09'))->toBe('2026-10-09')
            ->and($calendar->anchor('2026-10'))->toBe('2026-10-01');
    });

    it('takes the local date of a date object, in the viewer timezone', function (DateTimeInterface $value, string $viewer, string $expected) {
        calendarTestViewer($viewer);

        expect(Calendar::make()->anchor($value))->toBe($expected)
            ->and(Calendar::make()->window($value)['anchor'])->toBe($expected);
    })->with([
        'Carbon, already the next day for the viewer' => [fn () => Carbon::parse('2026-10-09 20:00:00', 'UTC'), 'Asia/Kuala_Lumpur', '2026-10-10'],
        'Carbon, the day before for the viewer' => [fn () => Carbon::parse('2026-10-09 03:00:00', 'UTC'), 'America/Los_Angeles', '2026-10-08'],
        'Carbon in the viewer zone itself' => [fn () => Carbon::parse('2026-10-09 23:30:00', 'Asia/Kuala_Lumpur'), 'Asia/Kuala_Lumpur', '2026-10-09'],
        'an immutable one' => [fn () => new DateTimeImmutable('2026-10-09 20:00:00', new DateTimeZone('UTC')), 'Asia/Kuala_Lumpur', '2026-10-10'],
        'a plain DateTime' => [fn () => new DateTime('2026-03-14 12:00:00', new DateTimeZone('UTC')), 'UTC', '2026-03-14'],
    ]);

    it('falls back to today for a date object outside the years it supports', function () {
        Carbon::setTestNow('2026-03-14 12:00:00');

        expect(Calendar::make()->anchor(Carbon::create(9999, 12, 31, 12, 0, 0, 'UTC')))->toBe('2026-03-14')
            ->and(Calendar::make()->anchor(Carbon::create(500, 1, 1, 12, 0, 0, 'UTC')))->toBe('2026-03-14');
    });

    it('falls back to the current month for a value a client could send', function (mixed $value) {
        Carbon::setTestNow('2026-03-14 12:00:00');

        $window = Calendar::make()->window($value);

        expect($window['anchor'])->toBe('2026-03-14')
            ->and($window['start'])->toBe('2026-02-23');
    })->with([
        'month 13' => ['2026-13'],
        'text' => ['x'],
        'five-digit year' => ['99999-01'],
        'a date that does not exist' => ['2026-02-30'],
        'month 00' => ['2026-00'],
        'empty' => [''],
        'null' => [null],
        'an array' => [['2026-10']],
        'a number' => [20261009],
        'trailing text' => ["2026-10-09\nx"],
        'a very old year' => ['0001-01'],
        // a window from these would run into year 10000, which Carbon cannot parse
        'the last month of 9999' => ['9999-12'],
        'the month before it' => ['9999-11'],
        'the first year past the cap' => ['9001-01'],
        'a day in 9999' => ['9999-12-31'],
    ]);

    it('accepts the first year the calendar supports, and builds its window', function () {
        calendarTestViewer('America/Los_Angeles');

        $window = Calendar::make()->window('1000-01');

        expect($window['anchor'])->toBe('1000-01-01')
            ->and($window['start'])->toBe('0999-12-30')
            ->and($window['days'])->toHaveCount(42)
            ->and($window['from'])->toBeInstanceOf(Carbon::class)
            ->and($window['to']->greaterThan($window['from']))->toBeTrue();
    });

    it('accepts the last year the calendar supports, and builds its window', function () {
        $window = Calendar::make()->window('9000-12');

        // the last month of the cap still closes inside four-digit years
        expect($window['anchor'])->toBe('9000-12-01')
            ->and($window['start'])->toBe('9000-12-01')
            ->and($window['end'])->toBe('9001-01-11')
            ->and($window['to'])->toBeInstanceOf(Carbon::class);
    });
});

describe('calendar today', function () {
    it('is the viewer local date, not the UTC date', function (string $viewer, string $today) {
        Carbon::setTestNow('2026-10-09 17:30:00');
        calendarTestViewer($viewer);

        expect(Calendar::make()->today())->toBe($today);
    })->with([
        'ahead of UTC, already tomorrow' => ['Asia/Kuala_Lumpur', '2026-10-10'],
        'behind UTC' => ['America/Los_Angeles', '2026-10-09'],
        'UTC' => ['UTC', '2026-10-09'],
    ]);

    it('carries the local clock for the now indicator', function () {
        Carbon::setTestNow('2026-10-09 17:30:15');
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(Calendar::make()->clock())->toBe('2026-10-10T01:30:15');
    });

    it('uses the local timezone when nobody is signed in', function () {
        Carbon::setTestNow('2026-10-09 17:30:00');

        $previous = [$_ENV['TIMEZONE'] ?? null, $_SERVER['TIMEZONE'] ?? null];
        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'Asia/Kuala_Lumpur';

        try {
            expect(Calendar::timezone())->toBe('Asia/Kuala_Lumpur')
                ->and(Calendar::make()->today())->toBe('2026-10-10');
        } finally {
            foreach (['_ENV' => $previous[0], '_SERVER' => $previous[1]] as $bag => $value) {
                if ($value === null) {
                    unset($GLOBALS[$bag]['TIMEZONE']);
                } else {
                    $GLOBALS[$bag]['TIMEZONE'] = $value;
                }
            }
        }
    });
});

describe('calendar fromLocal', function () {
    it('turns a local wall clock into an app-timezone instant', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        $instant = Calendar::fromLocal('2026-10-09T09:30');

        expect($instant->toIso8601ZuluString())->toBe('2026-10-09T01:30:00Z')
            ->and($instant->getTimezone()->getName())->toBe('UTC');
    });

    it('reads the viewer timezone, so the same wall clock is a different instant', function () {
        calendarTestViewer('America/Los_Angeles');

        expect(Calendar::fromLocal('2026-10-09T09:30')->toIso8601ZuluString())->toBe('2026-10-09T16:30:00Z');
    });

    it('answers in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarTestViewer('Asia/Kuala_Lumpur');

        $instant = Calendar::fromLocal('2026-10-09 09:30:00');

        expect($instant->getTimezone()->getName())->toBe('America/New_York')
            ->and($instant->toIso8601ZuluString())->toBe('2026-10-09T01:30:00Z');
    });

    it('turns a plain date into local midnight', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(Calendar::fromLocal('2026-10-09')->toIso8601ZuluString())->toBe('2026-10-08T16:00:00Z');
    });

    it('moves a wall clock that the clocks skipped forward, as PHP does', function () {
        calendarTestViewer('America/Los_Angeles');

        // 02:30 did not exist on 8 March 2026: the clocks went from 02:00 to 03:00
        $instant = Calendar::fromLocal('2026-03-08T02:30');

        expect($instant->toIso8601ZuluString())->toBe('2026-03-08T10:30:00Z')
            ->and($instant->copy()->setTimezone('America/Los_Angeles')->format('H:i T'))->toBe('03:30 PDT');
    });

    it('takes the first occurrence of a wall clock that the clocks repeated', function () {
        calendarTestViewer('America/Los_Angeles');

        // 01:30 happened twice on 1 November 2026; the first is still PDT (UTC-7)
        $instant = Calendar::fromLocal('2026-11-01T01:30');

        expect($instant->toIso8601ZuluString())->toBe('2026-11-01T08:30:00Z')
            ->and($instant->copy()->setTimezone('America/Los_Angeles')->format('H:i T'))->toBe('01:30 PDT');
    });

    it('refuses what the calendar never sends', function (string $value) {
        Calendar::fromLocal($value);
    })->with([
        'text' => ['tomorrow'],
        'an instant' => ['2026-10-09T09:30:00Z'],
        'an offset' => ['2026-10-09T09:30:00+08:00'],
        'a date that does not exist' => ['2026-02-30T09:30'],
        'an hour that does not exist' => ['2026-10-09T25:00'],
        'empty' => [''],
    ])->throws(InvalidArgumentException::class);
});

describe('calendar item times', function () {
    it('shows an instant as the viewer local wall clock', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-08T17:30:00Z']))
            ->toMatchArray(['start' => '2026-10-09T01:30', 'allDay' => false, 'end' => null]);
    });

    it('treats a Carbon or DateTime as the instant it is, in whatever zone it holds', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => Carbon::parse('2026-10-08 17:30:00', 'UTC')])['start'])->toBe('2026-10-09T01:30')
            ->and(calendarTestItem(['start' => new DateTimeImmutable('2026-10-09 09:30:00', new DateTimeZone('Asia/Kuala_Lumpur'))])['start'])->toBe('2026-10-09T09:30')
            ->and(calendarTestItem(['start' => new DateTime('2026-10-08 10:30:00', new DateTimeZone('America/Los_Angeles'))])['start'])->toBe('2026-10-09T01:30');
    });

    it('reads an offset exactly', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-09T09:30:00+08:00'])['start'])->toBe('2026-10-09T09:30')
            ->and(calendarTestItem(['start' => '2026-10-09T09:30:00-07:00'])['start'])->toBe('2026-10-10T00:30')
            ->and(calendarTestItem(['start' => '2026-10-09T09:30:00+0800'])['start'])->toBe('2026-10-09T09:30');
    });

    it('reads a string with no offset in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarTestViewer('Asia/Kuala_Lumpur');

        // 09:30 in New York is 13:30Z, which is 21:30 in Kuala Lumpur
        expect(calendarTestItem(['start' => '2026-10-08 09:30:00'])['start'])->toBe('2026-10-08T21:30');
    });

    it('reads a database timestamp in UTC when the app timezone is UTC', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-08 17:30:00'])['start'])->toBe('2026-10-09T01:30');
    });

    it('never converts a plain date', function (string $viewer) {
        calendarTestViewer($viewer);

        expect(calendarTestItem(['start' => '2026-10-09']))
            ->toMatchArray(['start' => '2026-10-09', 'allDay' => true, 'end' => null]);
    })->with(['Asia/Kuala_Lumpur', 'America/Los_Angeles', 'Pacific/Kiritimati', 'UTC']);

    it('gives an all-day instant the local date of that instant', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-08T17:30:00Z', 'all_day' => true]))
            ->toMatchArray(['start' => '2026-10-09', 'allDay' => true]);

        calendarTestViewer('America/Los_Angeles');

        expect(calendarTestItem(['start' => '2026-10-09T05:00:00Z', 'all_day' => true])['start'])->toBe('2026-10-08');
    });

    it('lets a timed item start on a plain date at local midnight', function () {
        expect(calendarTestItem(['start' => '2026-10-09', 'all_day' => false]))
            ->toMatchArray(['start' => '2026-10-09T00:00', 'allDay' => false]);
    });

    it('keeps a date end inclusive', function () {
        expect(calendarTestItem(['start' => '2026-10-09', 'end' => '2026-10-11']))
            ->toMatchArray(['start' => '2026-10-09', 'end' => '2026-10-11']);
    });

    it('ends an all-day instant end one second before it, so midnight does not claim another day', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        // local 2026-10-09 00:00 to 2026-10-12 00:00 covers the 9th, 10th and 11th
        expect(calendarTestItem(['start' => '2026-10-08T16:00:00Z', 'end' => '2026-10-11T16:00:00Z', 'all_day' => true]))
            ->toMatchArray(['start' => '2026-10-09', 'end' => '2026-10-11']);
    });

    it('keeps a timed end exclusive', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-09T01:00:00Z', 'end' => '2026-10-09T03:30:00Z']))
            ->toMatchArray(['start' => '2026-10-09T09:00', 'end' => '2026-10-09T11:30']);
    });

    it('drops an end that is not after the start', function (string $end) {
        Exceptions::fake();

        expect(calendarTestItem(['start' => '2026-10-09T09:30:00Z', 'end' => $end])['end'])->toBeNull();
    })->with([
        'the same moment' => ['2026-10-09T09:30:00Z'],
        'before it' => ['2026-10-09T08:00:00Z'],
        'not a date' => ['later'],
    ]);

    it('reports an end it cannot read, once, and keeps the item', function () {
        Exceptions::fake();

        $item = ['id' => 'bad-end-'.uniqid(), 'title' => 'T', 'start' => '2026-10-09T09:30:00Z', 'end' => 'later'];

        $items = Calendar::make()->items([$item]);
        Calendar::make()->items([$item]);

        expect($items)->toHaveCount(1)
            ->and($items[0]['end'])->toBeNull();

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (InvalidArgumentException $e) => str_contains($e->getMessage(), 'unreadable end'));
    });

    it('does not report an end that reads fine but is not after the start', function () {
        Exceptions::fake();

        calendarTestItem(['id' => 'early-end-'.uniqid(), 'start' => '2026-10-09T09:30:00Z', 'end' => '2026-10-09T08:00:00Z']);

        Exceptions::assertNothingReported();
    });

    it('keeps reporting after it has reported a lot of distinct values', function () {
        Exceptions::fake();

        $items = collect(range(1, CalendarItem::REPORTED_LIMIT + 1))
            ->map(fn ($i) => ['id' => 'flood-'.uniqid().'-'.$i, 'title' => 'T', 'start' => 'not a date'])
            ->all();

        Calendar::make()->items($items);

        // a set that stopped at its cap would have gone quiet for the last one
        Exceptions::assertReportedCount(CalendarItem::REPORTED_LIMIT + 1);
    });

    it('does not put a raw id in what it reports', function () {
        Exceptions::fake();

        $id = "bad\n<script>".uniqid().str_repeat('z', 200);

        Calendar::make()->items([['id' => $id, 'title' => 'T', 'start' => 'not a date']]);

        Exceptions::assertReported(fn (InvalidArgumentException $e) => ! str_contains($e->getMessage(), "\n")
            && ! str_contains($e->getMessage(), $id)
            && strlen($e->getMessage()) < 120);
    });

    it('truncates seconds', function () {
        calendarTestViewer('UTC');

        expect(calendarTestItem(['start' => '2026-10-09T09:30:45Z'])['start'])->toBe('2026-10-09T09:30');
    });

    it('accepts fractional seconds, as a model serialises them', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        expect(calendarTestItem(['start' => '2026-10-08T17:30:00.000000Z'])['start'])->toBe('2026-10-09T01:30');
    });

    it('skips an item whose start will not parse, and reports it once', function (mixed $start) {
        Exceptions::fake();

        $id = 'bad-start-'.md5(serialize($start));

        $items = Calendar::make()->items([
            ['id' => $id, 'title' => 'Bad', 'start' => $start],
            ['id' => 'good', 'title' => 'Good', 'start' => '2026-10-09'],
        ]);

        // the same item again, on a second render
        Calendar::make()->items([['id' => $id, 'title' => 'Bad', 'start' => $start]]);

        expect($items)->toHaveCount(1)->and($items[0]['id'])->toBe('good');

        Exceptions::assertReportedCount(1);
    })->with([
        'text' => ['next tuesday'],
        'a date that does not exist' => ['2026-02-30'],
        'an hour that does not exist' => ['2026-10-09T24:30'],
        'a timestamp' => [1791500000],
        'an array' => [[2026, 10, 9]],
    ]);
});

describe('calendar item validation', function () {
    it('throws on a missing id, title or start', function (array $item) {
        Calendar::make()->items([$item]);
    })->with([
        'no id' => [['title' => 'T', 'start' => '2026-10-09']],
        'empty id' => [['id' => '', 'title' => 'T', 'start' => '2026-10-09']],
        'array id' => [['id' => [1], 'title' => 'T', 'start' => '2026-10-09']],
        'no title' => [['id' => 1, 'start' => '2026-10-09']],
        'null title' => [['id' => 1, 'title' => null, 'start' => '2026-10-09']],
        'array title' => [['id' => 1, 'title' => ['x'], 'start' => '2026-10-09']],
        'no start' => [['id' => 1, 'title' => 'T']],
        'null start' => [['id' => 1, 'title' => 'T', 'start' => null]],
        'empty start' => [['id' => 1, 'title' => 'T', 'start' => '']],
    ])->throws(InvalidArgumentException::class);

    it('throws on something that is not an item', function (mixed $item) {
        Calendar::make()->items([$item]);
    })->with([
        'a string' => ['2026-10-09'],
        'an object' => [new stdClass],
        'null' => [null],
    ])->throws(InvalidArgumentException::class);

    it('keeps the first item of a repeated id, drops the later one, and reports it once', function () {
        Exceptions::fake();

        $id = 'dup-'.uniqid();
        $items = [
            ['id' => $id, 'title' => 'First', 'start' => '2026-10-09'],
            ['id' => $id, 'title' => 'Second', 'start' => '2026-10-10'],
        ];

        $result = Calendar::make()->items($items);

        expect($result)->toHaveCount(1)
            ->and($result[0])->toMatchArray(['id' => $id, 'title' => 'First', 'start' => '2026-10-09']);

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (InvalidArgumentException $e) => str_contains($e->getMessage(), 'repeats an id'));
    });

    it('treats 1 and "1" as the same id, because the browser keys items by it', function () {
        Exceptions::fake();

        $id = random_int(100000, 999999);

        $result = Calendar::make()->items([
            ['id' => $id, 'title' => 'A', 'start' => '2026-10-09'],
            ['id' => (string) $id, 'title' => 'B', 'start' => '2026-10-10'],
        ]);

        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toBe('A');

        Exceptions::assertReportedCount(1);
    });

    it('keeps a message free of the raw id', function () {
        $id = "x\n<script>alert(1)</script>".str_repeat('y', 200);
        $messages = [];

        foreach ([
            [['id' => $id, 'start' => '2026-10-09']],
            [['id' => $id, 'title' => 'A']],
        ] as $items) {
            try {
                Calendar::make()->items($items);
            } catch (InvalidArgumentException $e) {
                $messages[] = $e->getMessage();
            }
        }

        expect($messages)->toHaveCount(2);

        foreach ($messages as $message) {
            expect($message)->not->toContain("\n")
                ->not->toContain('<script>alert(1)</script>'.str_repeat('y', 200))
                ->and(strlen($message))->toBeLessThan(120);
        }
    });

    it('does not put a raw id in what it reports for a repeated id', function () {
        Exceptions::fake();

        $id = "dup\n<script>".uniqid().str_repeat('z', 200);

        Calendar::make()->items([
            ['id' => $id, 'title' => 'A', 'start' => '2026-10-09'],
            ['id' => $id, 'title' => 'B', 'start' => '2026-10-10'],
        ]);

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (InvalidArgumentException $e) => ! str_contains($e->getMessage(), "\n")
            && ! str_contains($e->getMessage(), $id)
            && strlen($e->getMessage()) < 120);
    });

    it('accepts an Arrayable', function () {
        $item = new class implements Illuminate\Contracts\Support\Arrayable
        {
            public function toArray(): array
            {
                return ['id' => 7, 'title' => 'From a model', 'start' => '2026-10-09'];
            }
        };

        expect(Calendar::make()->items([$item])[0])->toMatchArray(['id' => 7, 'title' => 'From a model']);
    });

    it('keeps a title as text, for the browser to print as text', function () {
        expect(calendarTestItem(['title' => '<script>alert(1)</script>', 'start' => '2026-10-09'])['title'])
            ->toBe('<script>alert(1)</script>');
    });

    it('accepts a number as a title', function () {
        expect(calendarTestItem(['title' => 42, 'start' => '2026-10-09'])['title'])->toBe('42');
    });
});

describe('calendar item safety', function () {
    it('drops an href that is not safe, and keeps one that is', function (mixed $href, ?string $expected) {
        expect(calendarTestItem(['start' => '2026-10-09', 'href' => $href])['href'])->toBe($expected);
    })->with([
        'javascript' => ['javascript:alert(1)', null],
        'javascript with a tab' => ["java\tscript:alert(1)", null],
        'javascript, entity encoded' => ['javascript&colon;alert(1)', null],
        'data' => ['data:text/html,<script>alert(1)</script>', null],
        'an array' => [['x'], null],
        'empty' => ['', null],
        'a path' => ['/tasks/4', '/tasks/4'],
        'https' => ['https://example.com/a', 'https://example.com/a'],
    ]);

    it('drops a colour that is not a name or a strict hex', function (mixed $color, ?string $expected) {
        expect(calendarTestItem(['start' => '2026-10-09', 'color' => $color])['color'])->toBe($expected);
    })->with([
        'injected declaration' => ['#fff;position:fixed', null],
        'injected with a url' => ['#000;background:url(https://attacker.test/x)', null],
        'a brace' => ['#000}', null],
        'a four-digit hex is hex' => ['#AbCd', '#abcd'],
        'hex' => ['#AABBCC', '#aabbcc'],
        'short hex' => ['#abc', '#abc'],
        'bad length' => ['#abcde', null],
        'no hash' => ['aabbcc', null],
        'a name' => ['red', 'red'],
        'a name in capitals' => ['Blue', 'blue'],
        'unknown name' => ['chartreuse', null],
        'css function' => ['rgb(0,0,0)', null],
        'an array' => [['red'], null],
        'nothing' => [null, null],
    ]);

    it('takes a colour from an enum that has color()', function () {
        expect(calendarTestItem(['start' => '2026-10-09', 'color' => CalendarTestStatus::DONE])['color'])->toBe('green');
    });

    it('accepts a colour name added in config', function () {
        config(['atom.calendar.colors' => ['brand' => ['dot' => 'bg-brand'], 'Bad Name;' => []]]);

        expect(calendarTestItem(['start' => '2026-10-09', 'color' => 'brand'])['color'])->toBe('brand')
            ->and(CalendarItem::colors())->toContain('brand')->not->toContain('Bad Name;');
    });

    it('drops an icon that is not a real atom icon, without an error', function (mixed $icon, ?string $expected) {
        expect(calendarTestItem(['start' => '2026-10-09', 'icon' => $icon])['icon'])->toBe($expected);
    })->with([
        'a path' => ['../x', null],
        'a deeper path' => ['../../../etc/passwd', null],
        'a wrapper' => ['_wrapper', null],
        'the index' => ['index', null],
        'a slash' => ['icon/add', null],
        // these name a file that exists once the slug check is out of the way
        'up and into a sibling icon' => ['../icon/add', null],
        'the current directory' => ['./warning', null],
        'up and back down' => ['../icon/../icon/warning', null],
        // these name no file even without the slug check (there is no such directory, or file), so they only cover the shape
        'down and back up' => ['add/../warning', null],
        'the file suffix' => ['warning.blade', null],
        'missing' => ['no-such-icon', null],
        'capitals' => ['Warning', null],
        'an array' => [['warning'], null],
        'a real one' => ['warning', 'warning'],
    ]);

    it('applies the component defaults to drag and resize, and lets an item override them', function () {
        $items = Calendar::make(['draggable' => false])->items([
            ['id' => 1, 'title' => 'A', 'start' => '2026-10-09'],
            ['id' => 2, 'title' => 'B', 'start' => '2026-10-09', 'draggable' => true, 'resizable' => false],
        ]);

        expect($items[0])->toMatchArray(['draggable' => false, 'resizable' => true])
            ->and($items[1])->toMatchArray(['draggable' => true, 'resizable' => false]);
    });

    it('flags danger', function () {
        expect(calendarTestItem(['start' => '2026-10-09', 'danger' => true])['danger'])->toBeTrue()
            ->and(calendarTestItem(['start' => '2026-10-09'])['danger'])->toBeFalse();
    });
});

describe('calendar payload', function () {
    it('carries the anchor, today, the window, labels, config and items, and encodes as JSON', function () {
        Carbon::setTestNow('2026-10-09 10:30:00');
        calendarTestViewer('Asia/Kuala_Lumpur');

        $payload = Calendar::make(['views' => ['month', 'week']])->payload('2026-10', [
            ['id' => 1, 'title' => 'Standup', 'start' => '2026-10-09T01:30:00Z'],
        ]);

        expect($payload)->toHaveKeys(['anchor', 'today', 'timezone', 'window', 'labels', 'config', 'items'])
            ->and($payload)->not->toHaveKey('now')
            ->and($payload['anchor'])->toBe('2026-10-01')
            ->and($payload['today'])->toBe('2026-10-09')
            ->and($payload['timezone'])->toBe('Asia/Kuala_Lumpur')
            ->and($payload['window'])->toHaveKeys(['start', 'end', 'days'])
            ->and($payload['items'][0]['start'])->toBe('2026-10-09T09:30')
            ->and(json_encode($payload))->toBeString();
    });

    it('is the same payload at two moments of one day, since the clock is not part of it', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        $calendar = Calendar::make(['views' => ['month', 'week']]);
        $items = [['id' => 1, 'title' => 'Standup', 'start' => '2026-10-09T09:30']];

        Carbon::setTestNow('2026-10-09 10:30:00');
        $first = json_encode($calendar->payload('2026-10', $items));

        Carbon::setTestNow('2026-10-09 10:30:07');
        $second = json_encode($calendar->payload('2026-10', $items));

        expect($second)->toBe($first);
    });

    it('calls an items closure with the window instants', function () {
        calendarTestViewer('Asia/Kuala_Lumpur');

        $received = [];

        Calendar::make()->payload('2026-10-09', function ($from, $to) use (&$received) {
            $received = [$from->toIso8601ZuluString(), $to->toIso8601ZuluString()];

            return [];
        });

        expect($received)->toBe(['2026-09-27T16:00:00Z', '2026-11-08T15:59:59Z']);
    });

    it('treats a closure that returns null as no items', function () {
        expect(Calendar::make()->payload('2026-10-09', fn () => null)['items'])->toBe([]);
    });

    it('runs the closure with the window of a client anchor it had to repair', function () {
        Carbon::setTestNow('2026-03-14 12:00:00');

        $from = null;

        Calendar::make()->payload('99999-01', function ($f) use (&$from) {
            $from = $f;

            return [];
        });

        expect($from->toIso8601ZuluString())->toBe('2026-02-22T16:00:00Z');
    });

    it('lists the weekday labels from the week start', function () {
        $labels = Calendar::make(['week_start' => 'sunday'])->labels();

        expect(array_column($labels['weekdays'], 'key'))->toBe(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])
            ->and($labels['weekdays'][0])->toBe(['key' => 'sunday', 'short' => 'Sun', 'long' => 'Sunday'])
            ->and($labels['months'])->toHaveCount(12)
            ->and($labels['months'][9])->toBe('October');

        expect(Calendar::make()->labels()['weekdays'][0]['key'])->toBe('monday');
    });

    it('translates the weekday and month labels', function () {
        app()->setLocale('zh');

        $labels = Calendar::make()->labels();

        expect($labels['weekdays'][0]['long'])->not->toBe('Monday')
            ->and($labels['months'][9])->not->toBe('October');
    });
});

describe('calendar config', function () {
    it('defaults to a month view, Monday, a Saturday and Sunday weekend, 3 rows, 12-hour time and drag on', function () {
        expect(Calendar::make()->config())->toBe([
            'views' => ['month'],
            'view' => 'month',
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
        ]);
    });

    it('takes the props, and repairs one it does not understand', function () {
        $config = Calendar::make([
            'views' => ['day', 'Week', 'agenda', 'day', 7],
            'view' => 'week',
            'week_start' => 'Sunday',
            'weekend' => ['friday', 'saturday', 'funday'],
            'max' => null,
            'time_format' => 'H:i',
            'date_format' => 'D, j M Y',
            'selectable' => true,
            'draggable' => 'false',
            'resizable' => false,
            'slot_duration' => '15',
            'scroll_time' => '07:30',
            'now_indicator' => false,
        ])->config();

        expect($config)->toMatchArray([
            'views' => ['day', 'week'],
            'view' => 'week',
            'weekStart' => 'sunday',
            'weekend' => ['friday', 'saturday'],
            'max' => null,
            'timeFormat' => 'H:i',
            'dateFormat' => 'D, j M Y',
            'selectable' => true,
            'draggable' => false,
            'resizable' => false,
            'slotDuration' => 15,
            'scrollTime' => '07:30',
            'nowIndicator' => false,
        ]);
    });

    it('falls back for values that would reach the browser unchecked', function () {
        $config = Calendar::make([
            'views' => ['agenda'],
            'view' => 'year',
            'time_format' => 'Y-m-d"><script>',
            'date_format' => 'j M Y"><script>',
            'slot_duration' => 7,
            'scroll_time' => '25:99',
            'max' => 'many',
        ])->config();

        expect($config)->toMatchArray([
            'views' => ['month'],
            'view' => 'month',
            'timeFormat' => 'g:i A',
            'dateFormat' => 'j M Y',
            'slotDuration' => 30,
            'scrollTime' => '06:00',
            'max' => 3,
        ]);
    });

    it('shows every row for each way of saying so, and falls back to 3 for a number it cannot use', function (mixed $max, ?int $expected) {
        expect(Calendar::make(['max' => $max])->max)->toBe($expected);
    })->with([
        'null' => [null, null],
        'false' => [false, null],
        'zero' => [0, null],
        'zero as text' => ['0', null],
        'false as text' => ['false', null],
        'False in other case' => [' False ', null],
        'none' => ['none', null],
        'None in other case' => ['NONE', null],
        'a number' => [5, 5],
        'a number as text' => ['5', 5],
        'one' => [1, 1],
        'negative' => [-1, 3],
        'text' => ['many', 3],
        'true' => [true, 3],
        'an array' => [[2], 3],
    ]);

    it('opens on the first view when the requested one is not offered', function () {
        expect(Calendar::make(['views' => ['week', 'day'], 'view' => 'month'])->view)->toBe('week');
    });
});

describe('calendar display formats', function () {
    it('accepts a format built from the supported tokens and literals', function (string $format) {
        expect(Calendar::isFormat($format))->toBeTrue()
            ->and(Calendar::make(['time_format' => $format])->timeFormat)->toBe(
                strpbrk($format, Calendar::FORMAT_TOKENS['time']) !== false ? $format : Calendar::DEFAULT_TIME_FORMAT,
            );
    })->with([
        'g:i A', 'g:iA', 'h:i a', 'H:i', 'G:i', 'D, j M Y H:i', 'l j F Y, g:i a', 'd/m/y H:i', 'd-m-Y G.i',
        'Y年n月j日 H:i', 'j M (D) g:i A',
    ]);

    it('accepts a date format that holds a date token', function (string $format) {
        expect(Calendar::make(['date_format' => $format])->dateFormat)->toBe($format);
    })->with(['D, j M Y', 'l, j F Y', 'd/m/Y', 'Y-m-d', 'n/j/y', 'Y年n月j日']);

    it('refuses a format with a token outside the set, and falls back silently', function (string $format) {
        expect(Calendar::isFormat($format))->toBeFalse()
            ->and(Calendar::make(['time_format' => $format, 'date_format' => $format])->config())->toMatchArray([
                'timeFormat' => 'g:i A',
                'dateFormat' => 'j M Y',
            ]);
    })->with([
        'unix timestamp' => 'U',
        'timezone id' => 'e',
        'timezone abbreviation' => 'T',
        'microseconds' => 'u',
        'ordinal suffix, English only' => 'jS',
        'l jS' => 'l jS',
        'iso week' => 'W',
        'day of year' => 'z',
        'unpadded 24h with seconds' => 'H:i:s',
        'a literal word' => 'H:i \h',
        'a backslash' => 'H\\:i',
        'a letter that is not a token' => 'g:i x',
        'html' => 'Y-m-d"><script>',
        'a tag' => '<b>H:i</b>',
        'an ampersand' => 'H&i',
        'a quote' => "H:i'",
        'a backtick' => 'H`i',
        'a newline' => "H:i\n",
        'a tab' => "H\ti",
        'a NUL' => "H:i\0",
        'a right-to-left mark' => "H:i\u{200F}",
        'a line separator' => "H:i\u{2028}",
        'invalid UTF-8' => "H:i\xff",
        'empty' => '',
        'only literals' => ':- ,',
        'too long' => 'd/m/Y d/m/Y d/m/Y d/m/Y d/m/Y d/m/Y',
    ]);

    it('refuses what is not a string', function (mixed $format) {
        expect(Calendar::isFormat($format))->toBeFalse()
            ->and(Calendar::make(['time_format' => $format, 'date_format' => $format])->config())->toMatchArray([
                'timeFormat' => 'g:i A',
                'dateFormat' => 'j M Y',
            ]);
    })->with([
        'an array' => [['H:i']],
        'null' => [null],
        'an int' => [12],
        'a bool' => [true],
        'an object' => [new stdClass],
    ]);

    it('holds the length limit exactly', function () {
        $ok = str_repeat('d', 16).str_repeat('-', 16);

        expect(Calendar::isFormat($ok))->toBeTrue()
            ->and(Calendar::isFormat($ok.'-'))->toBeFalse()
            ->and(Calendar::isFormat(str_repeat('年', 31).'Y'))->toBeTrue()
            ->and(Calendar::isFormat(str_repeat('年', 32).'Y'))->toBeFalse();
    });

    it('needs a token of its own kind', function () {
        $config = Calendar::make(['time_format' => 'j M Y', 'date_format' => 'H:i'])->config();

        expect($config['timeFormat'])->toBe('g:i A')
            ->and($config['dateFormat'])->toBe('j M Y');

        expect(Calendar::make(['time_format' => 'j M g:i A', 'date_format' => 'j M g:i A'])->config())->toMatchArray([
            'timeFormat' => 'j M g:i A',
            'dateFormat' => 'j M g:i A',
        ]);
    });

    it('pins the supported tokens', function () {
        expect(Calendar::FORMAT_TOKENS)->toBe(['date' => 'djDlmnMFyY', 'time' => 'gGhHiaA'])
            ->and(Calendar::DEFAULT_TIME_FORMAT)->toBe('g:i A')
            ->and(Calendar::DEFAULT_DATE_FORMAT)->toBe('j M Y');
    });

    it('formats a date with every token', function () {
        $date = Carbon::parse('2026-03-02 15:05:00', 'UTC');

        expect(Calendar::format($date, 'd j D l m n M F y Y'))->toBe('02 2 Mon Monday 03 3 Mar March 26 2026')
            ->and(Calendar::format($date, 'g G h H i a A'))->toBe('3 15 03 15 05 pm PM')
            ->and(Calendar::format($date, 'D, j M Y'))->toBe('Mon, 2 Mar 2026')
            ->and(Calendar::format($date->setTime(0, 7), 'g:i A'))->toBe('12:07 AM')
            ->and(Calendar::format($date->setTime(12, 7), 'g:i a'))->toBe('12:07 pm')
            ->and(Calendar::format($date, 'Y年n月j日'))->toBe('2026年3月2日');
    });

    it('formats the wall clock it is given and leaves the date alone', function () {
        $date = Carbon::parse('2026-03-02 15:05:00', 'Asia/Kuala_Lumpur');

        app()->setLocale('zh');

        expect(Calendar::format($date, 'H:i'))->toBe('15:05')
            ->and($date->locale)->not->toBe('zh');
    });

    it('translates D, l, M, F, a and A for the viewer locale', function () {
        app()->setLocale('zh');

        $date = Carbon::parse('2026-10-05 15:05:00', 'UTC');

        expect(Calendar::format($date, 'D'))->toBe('周一')
            ->and(Calendar::format($date, 'l'))->toBe('星期一')
            ->and(Calendar::format($date, 'M'))->toBe('10月')
            ->and(Calendar::format($date, 'F'))->toBe('十月')
            ->and(Calendar::format($date, 'a'))->toBe('下午')
            ->and(Calendar::format($date, 'A'))->toBe('下午')
            ->and(Calendar::format($date->setTime(8, 0), 'A'))->toBe('早上')
            ->and(Calendar::format($date, 'j M Y'))->toBe('5 10月 2026');
    });

    it('throws on a format it would not accept', function (string $format) {
        Calendar::format(Carbon::parse('2026-10-05 15:05:00', 'UTC'), $format);
    })->with(['U', 'jS', "H:i\n", '<b>H</b>', 'H\\:i', ''])->throws(InvalidArgumentException::class);

    it('carries every name the browser formatter needs, the same as the server prints them', function (string $locale) {
        app()->setLocale($locale);

        $labels = Calendar::make(['week_start' => 'monday'])->labels();

        expect($labels['months'])->toHaveCount(12)
            ->and($labels['months_short'])->toHaveCount(12)
            ->and($labels['meridiem'])->not->toBeEmpty()
            ->and($labels['meridiem'][0][0])->toBe(0);

        foreach (range(1, 12) as $month) {
            $date = Carbon::create(2026, $month, 1, 0, 0, 0, 'UTC');

            expect(Calendar::format($date, 'F'))->toBe($labels['months'][$month - 1])
                ->and(Calendar::format($date, 'M'))->toBe($labels['months_short'][$month - 1]);
        }

        foreach ($labels['weekdays'] as $i => $weekday) {
            $date = Carbon::parse('2026-10-05', 'UTC')->addDays($i);

            expect(Calendar::format($date, 'D'))->toBe($weekday['short'])
                ->and(Calendar::format($date, 'l'))->toBe($weekday['long']);
        }

        foreach (range(0, 23) as $hour) {
            $date = Carbon::parse('2026-10-05', 'UTC')->setTime($hour, 0);
            $period = collect($labels['meridiem'])->last(fn ($transition) => $transition[0] <= $hour * 60);

            expect(Calendar::format($date, 'a'))->toBe($period[1])
                ->and(Calendar::format($date, 'A'))->toBe($period[2]);
        }
    })->with(['en', 'zh', 'ms', 'fr']);

    it('has English lower and upper case meridiems, and translates them under zh', function () {
        $english = Calendar::make()->labels();

        expect($english['meridiem'])->toBe([[0, 'am', 'AM'], [720, 'pm', 'PM']])
            ->and($english['months_short'][9])->toBe('Oct');

        app()->setLocale('zh');

        $chinese = Calendar::make()->labels();

        expect($chinese['meridiem'])->toBe([[0, '凌晨', '凌晨'], [360, '早上', '早上'], [540, '上午', '上午'], [690, '中午', '中午'], [750, '下午', '下午'], [1080, '晚上', '晚上']])
            ->and($chinese['months_short'][9])->toBe('10月');
    });
});
