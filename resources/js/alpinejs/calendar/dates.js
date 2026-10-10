// Wall-clock date arithmetic for <atom:calendar>.
//
// The server hands the browser local wall-clock strings and nothing is ever converted
// here, so the browser's own timezone (and its DST) must not leak in. A date is
// `YYYY-MM-DD`, a moment is `YYYY-MM-DDTHH:mm`, and days are added in UTC milliseconds,
// which have no daylight saving. No dayjs: the bundle shares one instance and the
// calendar needs none of its parsing.

const DAY = 86400000

const pad = (n, length = 2) => String(n).padStart(length, '0')

export const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

// A `Y-m-d` (or longer) string as numbers, or null when it is not a real date.
export const parseDate = (value) => {
    let match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value))

    if (!match) return null

    let [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])]
    let time = Date.UTC(y, m - 1, d)
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

export const addDays = (date, days) => toDate(parseDate(date).time + days * DAY)

export const diffDays = (from, to) => Math.round((parseDate(to).time - parseDate(from).time) / DAY)

// 1 (Monday) to 7 (Sunday)
export const isoWeekday = (date) => new Date(parseDate(date).time).getUTCDay() || 7

export const monthOf = (date) => String(date).slice(0, 7)

// Move by whole months, keeping the day of the month or the last day of a shorter one.
export const addMonths = (date, months) => {
    let { y, m, d } = parseDate(date)
    let index = y * 12 + (m - 1) + months
    let year = Math.floor(index / 12)
    let month = ((index % 12) + 12) % 12
    let last = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()

    return pad(year, 4) + '-' + pad(month + 1) + '-' + pad(Math.min(d, last))
}

// The date picker's instant as the local calendar day the user clicked: the one place
// the browser's own timezone is meant to be read.
export const fromLocalDate = (value) => {
    let date = new Date(value)

    if (Number.isNaN(date.getTime())) return null

    return pad(date.getFullYear(), 4) + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
}

// A calendar day as the browser-local midnight the date picker takes as its value.
export const toLocalDate = (date) => {
    let { y, m, d } = parseDate(date)

    return new Date(y, m - 1, d).toISOString()
}
