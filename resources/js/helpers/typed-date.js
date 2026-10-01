import dayjs from 'dayjs'

// Parse a date the user typed into a date picker's text input. Returns
// { date, hasTime } or null when the text is not a whole, real date.
//
// Accepted: the display format the picker prints (`01 Oct 2024`, full or short
// month name, any case), day-first numerics (`01/10/2024`, `1-10-2024`,
// `1.10.2024`) and ISO (`2024-10-01`). Numerics are always DAY first; there is
// no month-first reading. With `time`, a date may be followed by a 12h
// (`9:30 PM`) or 24h (`21:30`) time; without it, a time is refused. A partial
// entry or an impossible date (`31/02/2024`) is null.
//
// Hand-rolled on purpose: dayjs's customParseFormat plugin would extend the ONE
// dayjs instance the whole bundle shares, and the String prototype helpers call
// `dayjs(value, format)` expecting the format to be ignored (with the plugin, a
// `...Z` string loses its zone and a date-only string turns invalid).
const months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
const monthNames = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']

const datePatterns = [
    // 01 Oct 2024, 1 October 2024
    [/^(\d{1,2}) ([a-z]+) (\d{4})/, m => [m[3], monthOf(m[2]), m[1]]],
    // 01/10/2024, 1-10-2024, 1.10.2024 (one separator throughout)
    [/^(\d{1,2})([/.-])(\d{1,2})\2(\d{4})/, m => [m[4], Number(m[3]), m[1]]],
    // 2024-10-01
    [/^(\d{4})-(\d{1,2})-(\d{1,2})/, m => [m[1], Number(m[2]), m[3]]],
]

const monthOf = (word) => {
    let index = months.indexOf(word)
    if (index === -1) index = monthNames.indexOf(word)
    return index === -1 ? null : index + 1
}

// What may follow the date: nothing, or (with `time`) a 12h or 24h time.
const timePattern = /^ (\d{1,2}):(\d{2}) ?(am|pm)?$/

const parseTime = (rest) => {
    let m = rest.match(timePattern)
    if (!m) return null

    let hour = Number(m[1])
    let minute = Number(m[2])
    if (minute > 59) return null

    if (m[3]) {
        if (hour < 1 || hour > 12) return null
        hour = hour % 12 + (m[3] === 'pm' ? 12 : 0)
    }
    else if (hour > 23) return null

    return [hour, minute]
}

export const parseTypedDate = (text, { time = false } = {}) => {
    let value = String(text ?? '').trim().replace(/\s+/g, ' ').toLowerCase()
    if (!value) return null

    for (let [pattern, read] of datePatterns) {
        let m = value.match(pattern)
        if (!m) continue

        let [year, month, day] = read(m).map(Number)
        if (!month) return null

        let rest = value.slice(m[0].length)
        let clock = rest ? (time ? parseTime(rest) : null) : [0, 0]
        if (!clock) return null

        let date = new Date(year, month - 1, day, clock[0], clock[1])

        // Reject overflow (31/02 → 2 Mar) rather than let Date roll it over.
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null

        return { date: dayjs(date), hasTime: rest !== '' }
    }

    return null
}

// Split a typed range into its two ends: `a to b`, `a - b`, `a – b`, `a ~ b`.
// The separator needs space around it, so an ISO date's own dashes are not one.
// A single date is returned as one part.
export const splitTypedRange = (text) => String(text ?? '')
    .trim()
    .split(/\s+(?:to|-|–|—|~)\s+/i)
    .map(part => part.trim())
