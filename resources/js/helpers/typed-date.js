import dayjs from 'dayjs'
import customParseFormat from 'dayjs/plugin/customParseFormat.js'

dayjs.extend(customParseFormat)

// Parse a date the user typed into a date picker's text input. Returns
// { date, hasTime } or null when the text is not a whole, real date.
//
// Accepted: the display format the picker prints (`01 Oct 2024`, full or short
// month name, any case), day-first numerics (`01/10/2024`, `1-10-2024`,
// `1.10.2024`) and ISO (`2024-10-01`). Numerics are always DAY first; there is
// no month-first reading. With `time`, a date may be followed by a 12h
// (`9:30 PM`) or 24h (`21:30`) time; without it, a time is refused. Parsing is
// strict, so a partial entry or an impossible date (`31/02/2024`) is null.
const dateFormats = [
    'DD MMM YYYY', 'D MMM YYYY', 'DD MMMM YYYY', 'D MMMM YYYY',
    'DD/MM/YYYY', 'D/M/YYYY', 'DD/M/YYYY', 'D/MM/YYYY',
    'DD-MM-YYYY', 'D-M-YYYY', 'DD-M-YYYY', 'D-MM-YYYY',
    'DD.MM.YYYY', 'D.M.YYYY', 'DD.M.YYYY', 'D.MM.YYYY',
    'YYYY-MM-DD', 'YYYY-M-D',
]

const timeFormats = ['hh:mm A', 'h:mm A', 'hh:mmA', 'h:mmA', 'HH:mm', 'H:mm']

const dateTimeFormats = dateFormats.flatMap(date => timeFormats.map(time => `${date} ${time}`))

// Strict parsing compares case-sensitively, so `oct`/`OCT` → `Oct`, `pm` → `PM`.
const normalise = (text) => text
    .trim()
    .replace(/\s+/g, ' ')
    .replace(/[a-z]+/gi, word => /^(am|pm)$/i.test(word)
        ? word.toUpperCase()
        : word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())

export const parseTypedDate = (text, { time = false } = {}) => {
    let value = normalise(String(text ?? ''))
    if (!value) return null

    let date = dayjs(value, dateFormats, true)
    if (date.isValid()) return { date, hasTime: false }

    if (time) {
        date = dayjs(value, dateTimeFormats, true)
        if (date.isValid()) return { date, hasTime: true }
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
