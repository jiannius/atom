import { WEEKDAYS, isoWeekday, parseDate, parseTime } from './dates.js'

// The PHP date() tokens a display format may use. This is Calendar::FORMAT_TOKENS in
// src/Services/Calendar.php, and the two are pinned to each other by a test: a token added
// on one side only prints as a literal letter on the other.
export const TOKENS = {
    date: 'djDlmnMFyY',
    time: 'gGhHiaA',
}

const pad = (n, length = 2) => String(n).padStart(length, '0')

const isDay = (char) => char === 'd' || char === 'j'

// What `a` and `A` print at a minute of the day. The server sends the minutes at which they change
// ([[startMinute, lower, upper], ...]): by minute, because a locale such as zh names noon from 11:30.
// The one in force is the last that starts at or before this minute.
const period = (transitions, minute) => {
    let found = ['', '']

    for (let transition of Array.isArray(transitions) ? transitions : []) {
        if (transition[0] > minute) break

        found = [transition[1] ?? '', transition[2] ?? '']
    }

    return found
}

// Print a wall-clock date or moment with a display format, using the translated names the
// server sends (`labels`), so the result is what Calendar::format() prints. Anything that is
// not a token is a literal, as the format has no escape.
export const format = (moment, pattern, labels) => {
    let date = parseDate(moment)

    if (!date) return ''

    let { h, i } = parseTime(moment)
    let weekday = (labels.weekdays ?? []).find((day) => day.key === WEEKDAYS[isoWeekday(moment) - 1])
    let twelve = h % 12 || 12
    let chars = Array.from(String(pattern))

    // Some locales print a month name differently beside a day of the month (the genitive): the server sends
    // that form when it differs, and the one in force depends on where a day token stands in the pattern.
    let monthName = (token, standalone, at) => {
        let forms = labels.month_forms?.[token] ?? {}
        let list = chars.slice(0, at).some(isDay) ? forms.day_before : (chars.slice(at + 1).some(isDay) ? forms.day_after : null)

        return (list ?? standalone)?.[date.m - 1] ?? ''
    }

    let tokens = {
        d: () => pad(date.d),
        j: () => String(date.d),
        D: () => weekday?.short ?? '',
        l: () => weekday?.long ?? '',
        m: () => pad(date.m),
        n: () => String(date.m),
        M: (at) => monthName('M', labels.months_short, at),
        F: (at) => monthName('F', labels.months, at),
        y: () => pad(date.y % 100),
        Y: () => pad(date.y, 4),
        g: () => String(twelve),
        G: () => String(h),
        h: () => pad(twelve),
        H: () => pad(h),
        i: () => pad(i),
        a: () => period(labels.meridiem, h * 60 + i)[0],
        A: () => period(labels.meridiem, h * 60 + i)[1],
    }

    // by code point, so a literal like 年 passes through whole
    return chars.map((char, at) => Object.hasOwn(tokens, char) ? tokens[char](at) : char).join('')
}
