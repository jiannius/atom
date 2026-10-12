// Wall-clock date arithmetic for <atom:calendar>.
//
// The server hands the browser local wall-clock strings and nothing is ever converted
// here, so the browser's own timezone (and its DST) must not leak in. A date is
// `YYYY-MM-DD`, a moment is `YYYY-MM-DDTHH:mm`, and days are added in UTC milliseconds,
// which have no daylight saving. No dayjs: the bundle shares one instance and the
// calendar needs none of its parsing.

const DAY = 86400000

const pad = (n, length = 2) => String(n).padStart(length, '0')

// UTC midnight of a date (`month` counted from 0). Not Date.UTC(): that reads a year of 0 to 99 as 1900 to 1999.
const utc = (year, month, day) => {
    let date = new Date(0)

    date.setUTCFullYear(year, month, day)

    return date.getTime()
}

export const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

// A `Y-m-d` (or longer) string as numbers, or null when it is not a real date.
export const parseDate = (value) => {
    let match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value))

    if (!match) return null

    let [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])]
    let time = utc(y, m - 1, d)
    let date = new Date(time)

    if (date.getUTCFullYear() !== y || date.getUTCMonth() !== m - 1 || date.getUTCDate() !== d) return null

    return { y, m, d, time }
}

// A moment's wall-clock time as { h, i }; a bare date is midnight.
export const parseTime = (value) => {
    let match = /^\d{4}-\d{2}-\d{2}[T ](\d{2}):(\d{2})/.exec(String(value))

    return match ? { h: Number(match[1]), i: Number(match[2]) } : { h: 0, i: 0 }
}

export const toDate = (time) => {
    let date = new Date(time)

    return pad(date.getUTCFullYear(), 4) + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate())
}

export const dateOf = (moment) => String(moment).slice(0, 10)

// The wall-clock time of a moment as `HH:mm`, or null for a bare date.
export const timeOf = (moment) => String(moment).length > 10 ? String(moment).slice(11, 16) : null

// null when the date is not one, or the count is not a finite number: nothing here throws on a date it cannot read
export const addDays = (date, days) => {
    let from = parseDate(date)

    return from && Number.isFinite(days) ? toDate(from.time + days * DAY) : null
}

// NaN when either date is not one
export const diffDays = (from, to) => Math.round(((parseDate(to)?.time ?? NaN) - (parseDate(from)?.time ?? NaN)) / DAY)

// 1 (Monday) to 7 (Sunday)
export const isoWeekday = (date) => new Date(parseDate(date)?.time ?? NaN).getUTCDay() || 7

export const monthOf = (date) => String(date).slice(0, 7)

// Move by whole months, keeping the day of the month or the last day of a shorter one.
export const addMonths = (date, months) => {
    let from = parseDate(date)

    if (!from || !Number.isFinite(months)) return null

    let { y, m, d } = from
    let index = y * 12 + (m - 1) + months
    let year = Math.floor(index / 12)
    let month = ((index % 12) + 12) % 12
    let last = new Date(utc(year, month + 1, 0)).getUTCDate()

    return pad(year, 4) + '-' + pad(month + 1) + '-' + pad(Math.min(d, last))
}

// The date picker's instant as the local calendar day the user clicked: the one place
// the browser's own timezone is meant to be read.
export const fromLocalDate = (value) => {
    let date = new Date(value)

    if (Number.isNaN(date.getTime())) return null

    return pad(date.getFullYear(), 4) + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
}

// A calendar day as the browser-local midnight the date picker takes as its value; null when it is not a day the browser can hold.
export const toLocalDate = (date) => {
    let parsed = parseDate(date)

    if (!parsed) return null

    let local = new Date(parsed.y, parsed.m - 1, parsed.d)

    return Number.isNaN(local.getTime()) ? null : local.toISOString()
}

// A moment's time as minutes after midnight; a bare date is 0.
export const minuteOf = (moment) => {
    let { h, i } = parseTime(moment)

    return h * 60 + i
}

// What the clock reads `elapsed` milliseconds after the server said it was `seed` (`YYYY-MM-DDTHH:mm:ss`),
// as `YYYY-MM-DDTHH:mm`, or '' when the seed is not a moment. The wall clock simply carries on from the seed:
// nothing is converted, so a day with a daylight-saving change is off by the hour it moved by until the
// server says the time again.
export const nowAt = (seed, elapsed) => {
    let match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):(\d{2})/.exec(String(seed))

    if (!match || !parseDate(match[1]) || !Number.isFinite(elapsed)) return ''

    let seconds = Number(match[2]) * 3600 + Number(match[3]) * 60 + Number(match[4]) + Math.max(0, Math.floor(elapsed / 1000))
    let days = Math.floor(seconds / 86400)
    let rest = seconds - days * 86400

    return addDays(match[1], days) + 'T' + pad(Math.floor(rest / 3600)) + ':' + pad(Math.floor((rest % 3600) / 60))
}
