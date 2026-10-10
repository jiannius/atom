<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\CalendarItem;
use Jiannius\Atom\Services\Carbon;

enum CalendarItemStatus: string
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
function calendarViewer(string $timezone): void
{
    Auth::setUser(new GenericUser(['id' => 1, 'timezone' => $timezone]));
}

/**
 * The one normalised item for a host item.
 *
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function calendarItem(array $item): array
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
        calendarViewer('Asia/Kuala_Lumpur');

        $window = Calendar::make()->window('2026-10-09');

        expect($window['from']->toIso8601ZuluString())->toBe('2026-09-27T16:00:00Z')
            ->and($window['to']->toIso8601ZuluString())->toBe('2026-11-08T15:59:59Z');
    });

    it('computes the window in the viewer timezone, not the app timezone', function (string $viewer, string $from) {
        calendarViewer($viewer);

        expect(Calendar::make()->window('2026-10-09')['from']->toIso8601ZuluString())->toBe($from);
    })->with([
        'ahead of UTC' => ['Asia/Kuala_Lumpur', '2026-09-27T16:00:00Z'],
        'behind UTC' => ['America/Los_Angeles', '2026-09-28T07:00:00Z'],
        'UTC' => ['UTC', '2026-09-28T00:00:00Z'],
    ]);

    it('returns the window edges in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarViewer('Asia/Kuala_Lumpur');

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
        calendarViewer('America/Los_Angeles');

        $dates = collect(Calendar::make()->window('2026-11-15')['days'])->pluck('date');

        expect($dates->unique())->toHaveCount(42)
            ->and($dates->contains('2026-11-01'))->toBeTrue()
            ->and($dates->contains('2026-11-02'))->toBeTrue();
    });

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
    ]);
});

describe('calendar today', function () {
    it('is the viewer local date, not the UTC date', function (string $viewer, string $today) {
        Carbon::setTestNow('2026-10-09 17:30:00');
        calendarViewer($viewer);

        expect(Calendar::make()->today())->toBe($today);
    })->with([
        'ahead of UTC, already tomorrow' => ['Asia/Kuala_Lumpur', '2026-10-10'],
        'behind UTC' => ['America/Los_Angeles', '2026-10-09'],
        'UTC' => ['UTC', '2026-10-09'],
    ]);

    it('carries the local clock for the now indicator', function () {
        Carbon::setTestNow('2026-10-09 17:30:15');
        calendarViewer('Asia/Kuala_Lumpur');

        expect(Calendar::make()->clock())->toBe('2026-10-10T01:30:15');
    });

    it('uses the local timezone when nobody is signed in', function () {
        Carbon::setTestNow('2026-10-09 17:30:00');

        expect(Calendar::make()->today())->toBe('2026-10-10');
    });
});

describe('calendar fromLocal', function () {
    it('turns a local wall clock into an app-timezone instant', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        $instant = Calendar::fromLocal('2026-10-09T09:30');

        expect($instant->toIso8601ZuluString())->toBe('2026-10-09T01:30:00Z')
            ->and($instant->getTimezone()->getName())->toBe('UTC');
    });

    it('reads the viewer timezone, so the same wall clock is a different instant', function () {
        calendarViewer('America/Los_Angeles');

        expect(Calendar::fromLocal('2026-10-09T09:30')->toIso8601ZuluString())->toBe('2026-10-09T16:30:00Z');
    });

    it('answers in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarViewer('Asia/Kuala_Lumpur');

        $instant = Calendar::fromLocal('2026-10-09 09:30:00');

        expect($instant->getTimezone()->getName())->toBe('America/New_York')
            ->and($instant->toIso8601ZuluString())->toBe('2026-10-09T01:30:00Z');
    });

    it('turns a plain date into local midnight', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(Calendar::fromLocal('2026-10-09')->toIso8601ZuluString())->toBe('2026-10-08T16:00:00Z');
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
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-08T17:30:00Z']))
            ->toMatchArray(['start' => '2026-10-09T01:30', 'allDay' => false, 'end' => null]);
    });

    it('treats a Carbon or DateTime as the instant it is, in whatever zone it holds', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => Carbon::parse('2026-10-08 17:30:00', 'UTC')])['start'])->toBe('2026-10-09T01:30')
            ->and(calendarItem(['start' => new DateTimeImmutable('2026-10-09 09:30:00', new DateTimeZone('Asia/Kuala_Lumpur'))])['start'])->toBe('2026-10-09T09:30')
            ->and(calendarItem(['start' => new DateTime('2026-10-08 10:30:00', new DateTimeZone('America/Los_Angeles'))])['start'])->toBe('2026-10-09T01:30');
    });

    it('reads an offset exactly', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-09T09:30:00+08:00'])['start'])->toBe('2026-10-09T09:30')
            ->and(calendarItem(['start' => '2026-10-09T09:30:00-07:00'])['start'])->toBe('2026-10-10T00:30')
            ->and(calendarItem(['start' => '2026-10-09T09:30:00+0800'])['start'])->toBe('2026-10-09T09:30');
    });

    it('reads a string with no offset in the app timezone', function () {
        config(['app.timezone' => 'America/New_York']);
        calendarViewer('Asia/Kuala_Lumpur');

        // 09:30 in New York is 13:30Z, which is 21:30 in Kuala Lumpur
        expect(calendarItem(['start' => '2026-10-08 09:30:00'])['start'])->toBe('2026-10-08T21:30');
    });

    it('reads a database timestamp in UTC when the app timezone is UTC', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-08 17:30:00'])['start'])->toBe('2026-10-09T01:30');
    });

    it('never converts a plain date', function (string $viewer) {
        calendarViewer($viewer);

        expect(calendarItem(['start' => '2026-10-09']))
            ->toMatchArray(['start' => '2026-10-09', 'allDay' => true, 'end' => null]);
    })->with(['Asia/Kuala_Lumpur', 'America/Los_Angeles', 'Pacific/Kiritimati', 'UTC']);

    it('gives an all-day instant the local date of that instant', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-08T17:30:00Z', 'all_day' => true]))
            ->toMatchArray(['start' => '2026-10-09', 'allDay' => true]);

        calendarViewer('America/Los_Angeles');

        expect(calendarItem(['start' => '2026-10-09T05:00:00Z', 'all_day' => true])['start'])->toBe('2026-10-08');
    });

    it('lets a timed item start on a plain date at local midnight', function () {
        expect(calendarItem(['start' => '2026-10-09', 'all_day' => false]))
            ->toMatchArray(['start' => '2026-10-09T00:00', 'allDay' => false]);
    });

    it('keeps a date end inclusive', function () {
        expect(calendarItem(['start' => '2026-10-09', 'end' => '2026-10-11']))
            ->toMatchArray(['start' => '2026-10-09', 'end' => '2026-10-11']);
    });

    it('ends an all-day instant end one second before it, so midnight does not claim another day', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        // local 2026-10-09 00:00 to 2026-10-12 00:00 covers the 9th, 10th and 11th
        expect(calendarItem(['start' => '2026-10-08T16:00:00Z', 'end' => '2026-10-11T16:00:00Z', 'all_day' => true]))
            ->toMatchArray(['start' => '2026-10-09', 'end' => '2026-10-11']);
    });

    it('keeps a timed end exclusive', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-09T01:00:00Z', 'end' => '2026-10-09T03:30:00Z']))
            ->toMatchArray(['start' => '2026-10-09T09:00', 'end' => '2026-10-09T11:30']);
    });

    it('drops an end that is not after the start', function (string $end) {
        Exceptions::fake();

        expect(calendarItem(['start' => '2026-10-09T09:30:00Z', 'end' => $end])['end'])->toBeNull();
    })->with([
        'the same moment' => ['2026-10-09T09:30:00Z'],
        'before it' => ['2026-10-09T08:00:00Z'],
        'not a date' => ['later'],
    ]);

    it('truncates seconds', function () {
        calendarViewer('UTC');

        expect(calendarItem(['start' => '2026-10-09T09:30:45Z'])['start'])->toBe('2026-10-09T09:30');
    });

    it('accepts fractional seconds, as a model serialises them', function () {
        calendarViewer('Asia/Kuala_Lumpur');

        expect(calendarItem(['start' => '2026-10-08T17:30:00.000000Z'])['start'])->toBe('2026-10-09T01:30');
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

    it('throws on a repeated id', function () {
        Calendar::make()->items([
            ['id' => 1, 'title' => 'A', 'start' => '2026-10-09'],
            ['id' => 1, 'title' => 'B', 'start' => '2026-10-10'],
        ]);
    })->throws(InvalidArgumentException::class);

    it('keeps 1 and "1" apart, because the host gets the id back as it sent it', function () {
        expect(Calendar::make()->items([
            ['id' => 1, 'title' => 'A', 'start' => '2026-10-09'],
            ['id' => '1', 'title' => 'B', 'start' => '2026-10-10'],
        ]))->toHaveCount(2);
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
        expect(calendarItem(['title' => '<script>alert(1)</script>', 'start' => '2026-10-09'])['title'])
            ->toBe('<script>alert(1)</script>');
    });

    it('accepts a number as a title', function () {
        expect(calendarItem(['title' => 42, 'start' => '2026-10-09'])['title'])->toBe('42');
    });
});

describe('calendar item safety', function () {
    it('drops an href that is not safe, and keeps one that is', function (mixed $href, ?string $expected) {
        expect(calendarItem(['start' => '2026-10-09', 'href' => $href])['href'])->toBe($expected);
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
        expect(calendarItem(['start' => '2026-10-09', 'color' => $color])['color'])->toBe($expected);
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
        expect(calendarItem(['start' => '2026-10-09', 'color' => CalendarItemStatus::DONE])['color'])->toBe('green');
    });

    it('accepts a colour name added in config', function () {
        config(['atom.calendar.colors' => ['brand' => ['dot' => 'bg-brand'], 'Bad Name;' => []]]);

        expect(calendarItem(['start' => '2026-10-09', 'color' => 'brand'])['color'])->toBe('brand')
            ->and(CalendarItem::colors())->toContain('brand')->not->toContain('Bad Name;');
    });

    it('drops an icon that is not a real atom icon, without an error', function (mixed $icon, ?string $expected) {
        expect(calendarItem(['start' => '2026-10-09', 'icon' => $icon])['icon'])->toBe($expected);
    })->with([
        'a path' => ['../x', null],
        'a deeper path' => ['../../../etc/passwd', null],
        'a wrapper' => ['_wrapper', null],
        'the index' => ['index', null],
        'a slash' => ['icon/add', null],
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
        expect(calendarItem(['start' => '2026-10-09', 'danger' => true])['danger'])->toBeTrue()
            ->and(calendarItem(['start' => '2026-10-09'])['danger'])->toBeFalse();
    });
});

describe('calendar payload', function () {
    it('carries the anchor, today, the window, labels, config and items, and encodes as JSON', function () {
        Carbon::setTestNow('2026-10-09 10:30:00');
        calendarViewer('Asia/Kuala_Lumpur');

        $payload = Calendar::make(['views' => ['month', 'week']])->payload('2026-10', [
            ['id' => 1, 'title' => 'Standup', 'start' => '2026-10-09T01:30:00Z'],
        ]);

        expect($payload)->toHaveKeys(['anchor', 'today', 'now', 'timezone', 'window', 'labels', 'config', 'items'])
            ->and($payload['anchor'])->toBe('2026-10-01')
            ->and($payload['today'])->toBe('2026-10-09')
            ->and($payload['now'])->toBe('2026-10-09T18:30:00')
            ->and($payload['timezone'])->toBe('Asia/Kuala_Lumpur')
            ->and($payload['window'])->toHaveKeys(['start', 'end', 'days'])
            ->and($payload['items'][0]['start'])->toBe('2026-10-09T09:30')
            ->and(json_encode($payload))->toBeString();
    });

    it('calls an items closure with the window instants', function () {
        calendarViewer('Asia/Kuala_Lumpur');

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
            'slot_duration' => 7,
            'scroll_time' => '25:99',
            'max' => 0,
        ])->config();

        expect($config)->toMatchArray([
            'views' => ['month'],
            'view' => 'month',
            'timeFormat' => 'g:i A',
            'slotDuration' => 30,
            'scrollTime' => '06:00',
            'max' => 3,
        ]);
    });

    it('opens on the first view when the requested one is not offered', function () {
        expect(Calendar::make(['views' => ['week', 'day'], 'view' => 'month'])->view)->toBe('week');
    });
});
