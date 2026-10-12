import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { format, TOKENS } from '../../resources/js/alpinejs/calendar/format.js'

// The browser formatter against the corpus PHP's Calendar::format() wrote
// (tests/Fixtures/calendar-format-corpus.json, held to PHP by CalendarFormatParityTest). Nothing here
// opens a page: it is the formatter module run in node under the Playwright runner.

const corpus = JSON.parse(readFileSync(new URL('../Fixtures/calendar-format-corpus.json', import.meta.url), 'utf8'))

test('supports exactly the tokens the server accepts', () => {
    expect(TOKENS).toEqual(corpus.tokens)
})

for (const [locale, { labels, cases }] of Object.entries(corpus.locales)) {
    test(`prints what PHP prints in ${locale}`, () => {
        const wrong = cases
            .map(([moment, pattern, expected]) => ({ moment, pattern, expected, got: format(moment, pattern, labels) }))
            .filter(({ expected, got }) => expected !== got)

        expect(wrong).toEqual([])
        expect(cases.length).toBeGreaterThan(300)
    })
}

// Every minute of a day, held to what Calendar::format() printed (as runs of [startMinute, value]).
for (const [locale, { labels, sweep }] of Object.entries(corpus.locales)) {
    test(`prints the period of the day at every minute as PHP does in ${locale}`, () => {
        const at = (runs, minute) => runs.filter(([start]) => start <= minute).pop()[1]
        const wrong = []

        for (let minute = 0; minute < 1440; minute++) {
            const moment = '2026-10-05T' + String(Math.floor(minute / 60)).padStart(2, '0') + ':' + String(minute % 60).padStart(2, '0')

            for (const token of ['a', 'A']) {
                const got = format(moment, token, labels)

                if (got !== at(sweep[token], minute)) wrong.push({ moment, token, got, expected: at(sweep[token], minute) })
            }
        }

        expect(wrong).toEqual([])
    })
}

test('zh prints noon from 11:30 to 12:29 and the afternoon from 12:30', () => {
    const { labels } = corpus.locales.zh

    expect(['11:29', '11:30', '11:45', '12:29', '12:30', '12:45'].map((time) => format('2026-10-05T' + time, 'a', labels)))
        .toEqual(['上午', '中午', '中午', '中午', '下午', '下午'])
})

test('prints a month in the form its locale uses beside a day, by where the day stands in the pattern', () => {
    const { labels } = corpus.locales.lt

    // 2026-03-02: kovas on its own, kovo beside a day (lt takes it on either side)
    expect(format('2026-03-02', 'F Y', labels)).toBe('kovas 2026')
    expect(format('2026-03-02', 'j F', labels)).toBe('2 kovo')
    expect(format('2026-03-02', 'F j', labels)).toBe('kovo 2')

    const croatian = corpus.locales.hr.labels

    expect(format('2026-03-02', 'j F', croatian)).toBe('2 ožujka')
    expect(format('2026-03-02', 'F j', croatian)).toBe('ožujak 2')
})

test('every token prints something other than itself, and a letter that is not a token is a literal', () => {
    const { labels } = corpus.locales.en
    const tokens = TOKENS.date + TOKENS.time

    for (const token of tokens) {
        // 2026-10-09T13:05: no token can print its own letter here (the month name is not one letter long)
        expect(format('2026-10-09T13:05', token, labels), token).not.toBe(token)
    }

    for (const letter of 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') {
        if (!tokens.includes(letter)) {
            expect(format('2026-10-09T13:05', letter, labels), letter).toBe(letter)
        }
    }
})

test('a literal passes through whole, including characters outside ASCII', () => {
    const { labels } = corpus.locales.zh

    expect(format('2026-10-09T13:05', 'Y年n月j日 H:i', labels)).toBe('2026年10月9日 13:05')
    expect(format('2026-10-09T13:05', '😀 g', labels)).toBe('😀 1')
})

test('an unreadable moment prints nothing, and a label missing from the payload prints as empty', () => {
    const { labels } = corpus.locales.en

    expect(format('not a date', 'Y', labels)).toBe('')
    expect(format('2026-02-31', 'Y', labels)).toBe('')
    expect(format('2026-10-09', 'M F l a A', {})).toBe('    ')
})
