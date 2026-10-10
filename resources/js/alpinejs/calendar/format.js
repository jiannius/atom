import { WEEKDAYS, isoWeekday, parseDate, parseTime } from './dates.js'

// The PHP date() tokens a display format may use. This is Calendar::FORMAT_TOKENS in
// src/Services/Calendar.php, and the two are pinned to each other by a test: a token added
// on one side only prints as a literal letter on the other.
export const TOKENS = {
    date: 'djDlmnMFyY',
    time: 'gGhHiaA',
}

const pad = (n, length = 2) => String(n).padStart(length, '0')

// Print a wall-clock date or moment with a display format, using the translated names the
// server sends (`labels`), so the result is what Calendar::format() prints. Anything that is
// not a token is a literal, as the format has no escape.
export const format = (moment, pattern, labels) => {
    let date = parseDate(moment)

    if (!date) return ''

    let { h, i } = parseTime(moment)
    let weekday = (labels.weekdays ?? []).find((day) => day.key === WEEKDAYS[isoWeekday(moment) - 1])
    let twelve = h % 12 || 12

    let tokens = {
        d: () => pad(date.d),
        j: () => String(date.d),
        D: () => weekday?.short ?? '',
        l: () => weekday?.long ?? '',
        m: () => pad(date.m),
        n: () => String(date.m),
        M: () => labels.months_short?.[date.m - 1] ?? '',
        F: () => labels.months?.[date.m - 1] ?? '',
        y: () => pad(date.y % 100),
        Y: () => pad(date.y, 4),
        g: () => String(twelve),
        G: () => String(h),
        h: () => pad(twelve),
        H: () => pad(h),
        i: () => pad(i),
        a: () => labels.meridiem?.lower?.[h] ?? '',
        A: () => labels.meridiem?.upper?.[h] ?? '',
    }

    // by code point, so a literal like 年 passes through whole
    return Array.from(String(pattern)).map((char) => Object.hasOwn(tokens, char) ? tokens[char]() : char).join('')
}
