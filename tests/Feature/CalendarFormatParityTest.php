<?php

use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon;

// The corpus is what the PHP formatter printed, per locale, for a spread of moments and formats.
// The browser formatter (tests/e2e/calendar-format.spec.js) must print the same strings from the
// same labels, and this test fails when PHP stops printing them. Regenerate after a deliberate
// change with: CALENDAR_FORMAT_CORPUS=write vendor/bin/pest --filter=CalendarFormatParity

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
    ];

    $formats = [
        'd', 'j', 'D', 'l', 'm', 'n', 'M', 'F', 'y', 'Y', 'g', 'G', 'h', 'H', 'i', 'a', 'A',
        'g:i A', 'g:iA', 'h:i a', 'H:i', 'G:i', 'D, j M Y', 'l j F Y, g:i a', 'd/m/y H:i', 'd-m-Y G.i',
        'Y年n月j日 H:i', 'j M (D) g:i A', 'Y-m-d', 'l, j F Y', '12 g',
    ];

    $corpus = ['tokens' => Calendar::FORMAT_TOKENS, 'locales' => []];

    foreach (['en', 'zh', 'ms'] as $locale) {
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

    expect(json_decode(file_get_contents(CALENDAR_FORMAT_CORPUS_PATH), true))->toBe(json_decode(json_encode($corpus), true));
});

it('holds the corpus tokens to the supported set, so a token added on one side shows here', function () {
    $stored = json_decode(file_get_contents(CALENDAR_FORMAT_CORPUS_PATH), true);

    expect($stored['tokens'])->toBe(Calendar::FORMAT_TOKENS);
});

it('covers every token in every locale the corpus names', function () {
    $corpus = calendarFormatCorpus();

    expect(array_keys($corpus['locales']))->toBe(['en', 'zh', 'ms']);

    foreach (str_split(Calendar::FORMAT_TOKENS['date'].Calendar::FORMAT_TOKENS['time']) as $token) {
        foreach ($corpus['locales'] as $locale => $data) {
            expect(collect($data['cases'])->contains(fn ($case) => $case[1] === $token))->toBeTrue("$token missing for $locale");
        }
    }
});
