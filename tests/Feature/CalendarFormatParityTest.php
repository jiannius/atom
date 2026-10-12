<?php

use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon;

// The corpus is what the PHP formatter printed, per locale, for a spread of moments and formats.
// The browser formatter (tests/e2e/calendar-format.spec.js) must print the same strings from the
// same labels, and this test fails when PHP stops printing them. Regenerate after a deliberate
// change with: CALENDAR_FORMAT_CORPUS=write vendor/bin/pest --filter=CalendarFormatParity

/**
 * The locales the corpus covers: the three the calendar ships labels for, a locale with day periods that change inside an
 * hour (zh) or between hours (ms, hi), the ones where Carbon's own name lookups differ from translatedFormat() (es, nl, lv, hr,
 * lt), and the ones that print a month name differently beside a day (hr, lt, ru).
 */
const CALENDAR_FORMAT_CORPUS_LOCALES = ['en', 'zh', 'ms', 'hi', 'es', 'nl', 'lv', 'hr', 'lt', 'ru'];

/**
 * What `a` and `A` print at every minute of a day in the current locale, as runs of [startMinute, value] taken from
 * Calendar::format() itself, so the browser can be held to all 1440 minutes without the corpus holding 2880 strings.
 *
 * @return array{a: list<array{0: int, 1: string}>, A: list<array{0: int, 1: string}>}
 */
function calendarMeridiemSweep(): array
{
    $sweep = ['a' => [], 'A' => []];

    for ($minute = 0; $minute < 1440; $minute++) {
        $date = Carbon::parse('2026-10-05 00:00:00', 'UTC')->setTime(intdiv($minute, 60), $minute % 60);

        foreach (['a', 'A'] as $token) {
            $value = Calendar::format($date, $token);
            $last = $sweep[$token][array_key_last($sweep[$token])] ?? null;

            if (! $last || $last[1] !== $value) {
                $sweep[$token][] = [$minute, $value];
            }
        }
    }

    return $sweep;
}

/**
 * What the PHP formatter prints for every moment and format, per locale, with the labels the browser is sent.
 *
 * @return array{tokens: array<string, string>, locales: array<string, array{labels: array<string, mixed>, cases: list<array{0: string, 1: string, 2: string}>}>}
 */
function calendarFormatCorpus(): array
{
    $moments = [
        '2026-01-01T00:00', '2026-03-02T15:05', '2026-10-05T12:07', '2026-12-31T23:59', '2024-02-29T08:30',
        '2026-10-09', '1999-07-04T00:07', '2026-06-15T05:00', '2026-06-15T19:45', '2026-11-08T11:11',
        // zh names the period by the minute: 中午 from 11:30 to 12:29, 下午 from 12:30
        '2026-10-05T11:30', '2026-10-05T11:45', '2026-10-05T12:29', '2026-10-05T12:30', '2026-10-05T12:45',
    ];

    $formats = [
        'd', 'j', 'D', 'l', 'm', 'n', 'M', 'F', 'y', 'Y', 'g', 'G', 'h', 'H', 'i', 'a', 'A',
        'g:i A', 'g:iA', 'h:i a', 'H:i', 'G:i', 'D, j M Y', 'l j F Y, g:i a', 'd/m/y H:i', 'd-m-Y G.i',
        'Y年n月j日 H:i', 'j M (D) g:i A', 'Y-m-d', 'l, j F Y', '12 g', 'F j', 'M j, Y', 'F Y', 'F, l H:i', 'd F',
    ];

    $corpus = ['tokens' => Calendar::FORMAT_TOKENS, 'locales' => []];

    foreach (CALENDAR_FORMAT_CORPUS_LOCALES as $locale) {
        app()->setLocale($locale);

        $cases = [];

        foreach ($moments as $moment) {
            foreach ($formats as $format) {
                $cases[] = [$moment, $format, Calendar::format(Carbon::parse(str_replace('T', ' ', $moment), 'UTC'), $format)];
            }
        }

        $corpus['locales'][$locale] = [
            'labels' => Calendar::make(['week_start' => $locale === 'zh' ? 'sunday' : 'monday'])->labels(),
            'cases' => $cases,
            'sweep' => calendarMeridiemSweep(),
        ];
    }

    app()->setLocale('en');

    return $corpus;
}

const CALENDAR_FORMAT_CORPUS_PATH = __DIR__.'/../Fixtures/calendar-format-corpus.json';

it('prints what the corpus the browser formatter is held to says it prints', function () {
    $corpus = calendarFormatCorpus();

    if (getenv('CALENDAR_FORMAT_CORPUS') === 'write') {
        file_put_contents(CALENDAR_FORMAT_CORPUS_PATH, json_encode($corpus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    // a boolean, not toBe(): on a mismatch Pest would build a diff of the whole corpus, which takes minutes
    $stored = json_decode(file_get_contents(CALENDAR_FORMAT_CORPUS_PATH), true);
    $current = json_decode(json_encode($corpus), true);

    expect($stored === $current)->toBeTrue('the corpus is stale: '.implode(', ', array_keys(array_filter($current['locales'], fn ($data, $locale) => ($stored['locales'][$locale] ?? null) !== $data, ARRAY_FILTER_USE_BOTH))).' differ. Regenerate it with CALENDAR_FORMAT_CORPUS=write');
});

it('holds the corpus tokens to the supported set, so a token added on one side shows here', function () {
    $stored = json_decode(file_get_contents(CALENDAR_FORMAT_CORPUS_PATH), true);

    expect($stored['tokens'])->toBe(Calendar::FORMAT_TOKENS);
});

it('covers every token in every locale the corpus names', function () {
    $corpus = calendarFormatCorpus();

    expect(array_keys($corpus['locales']))->toBe(CALENDAR_FORMAT_CORPUS_LOCALES);

    foreach (str_split(Calendar::FORMAT_TOKENS['date'].Calendar::FORMAT_TOKENS['time']) as $token) {
        foreach ($corpus['locales'] as $locale => $data) {
            expect(collect($data['cases'])->contains(fn ($case) => $case[1] === $token))->toBeTrue("$token missing for $locale");
        }
    }
});

it('labels `a` and `A` at every minute of the day the way format() prints them', function (string $locale) {
    app()->setLocale($locale);

    $transitions = Calendar::make()->labels()['meridiem'];

    for ($minute = 0; $minute < 1440; $minute++) {
        $date = Carbon::parse('2026-10-05 00:00:00', 'UTC')->setTime(intdiv($minute, 60), $minute % 60);
        $period = collect($transitions)->last(fn ($transition) => $transition[0] <= $minute);

        expect([$period[1], $period[2]])->toBe([Calendar::format($date, 'a'), Calendar::format($date, 'A')], sprintf('%s at %02d:%02d', $locale, intdiv($minute, 60), $minute % 60));
    }
})->with(['en', 'zh', 'ms', 'hi', 'id']);

it('puts zh noon at 11:30 and 12:30, where an hourly table says otherwise', function () {
    app()->setLocale('zh');

    $transitions = Calendar::make()->labels()['meridiem'];

    expect($transitions)->toContain([690, '中午', '中午'])
        ->and($transitions)->toContain([750, '下午', '下午']);
});

it('labels every month and weekday the way format() prints it, in the locales where Carbon name lookups differ', function (string $locale) {
    app()->setLocale($locale);

    $labels = Calendar::make(['week_start' => 'monday'])->labels();

    foreach (range(1, 12) as $month) {
        $date = Carbon::create(2026, $month, 1, 0, 0, 0, 'UTC');

        expect($labels['months'][$month - 1])->toBe(Calendar::format($date, 'F'), "$locale F $month")
            ->and($labels['months_short'][$month - 1])->toBe(Calendar::format($date, 'M'), "$locale M $month");
    }

    foreach ($labels['weekdays'] as $i => $weekday) {
        $date = Carbon::parse('2026-10-05', 'UTC')->addDays($i);

        expect($weekday['short'])->toBe(Calendar::format($date, 'D'), "$locale D $i")
            ->and($weekday['long'])->toBe(Calendar::format($date, 'l'), "$locale l $i");
    }
})->with(['es', 'nl', 'lv', 'hr', 'lt', 'ru']);

it('sends the month names a locale prints beside a day only where they differ from the standalone ones', function () {
    app()->setLocale('ru');
    $russian = Calendar::make()->labels();

    app()->setLocale('en');
    $english = Calendar::make()->labels();

    expect($english['month_forms'])->toBe([])
        ->and($russian['month_forms']['F']['day_before'][2])->toBe('марта')
        ->and($russian['months'][2])->toBe('март');
});
