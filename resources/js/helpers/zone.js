import dayjs from 'dayjs'

// The date pickers' timezone (`<atom:date-picker timezone="Asia/Kuala_Lumpur">`).
//
// The shared dayjs has no plugin (the calendar and `atom.str` rely on that, and
// typed-date.js says why a parse plugin is off the table), so the zone maths here
// is `Intl.DateTimeFormat` and nothing else.
//
// The pickers keep working on dayjs objects, which Pikaday and the typed-date parser
// already speak. In a zone those objects are WALL CLOCKS: a dayjs whose browser-local
// fields (year ... millisecond) read the time on the wall in the zone. `read()` turns
// a stored instant into one, `write()` / `start()` / `end()` turn one back into an
// ISO UTC string. Without a zone (or with one the browser does not know) every method
// is exactly what the pickers did before: plain browser-local dayjs.
const formatters = new Map()

const formatterFor = (timezone) => {
    if (!formatters.has(timezone)) {
        formatters.set(timezone, new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            hourCycle: 'h23',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
        }))
    }

    return formatters.get(timezone)
}

// The zone's name if the browser knows it, else null (an empty prop, a typo).
export const validZone = (timezone) => {
    if (typeof timezone !== 'string' || !timezone.trim()) return null

    try {
        formatterFor(timezone.trim())
        return timezone.trim()
    }
    catch (e) {
        return null
    }
}

// The wall-clock fields of an instant (ms since the epoch) in a zone.
export const zoneParts = (ms, timezone) => {
    let parts = {}
    for (let { type, value } of formatterFor(timezone).formatToParts(new Date(ms))) parts[type] = Number(value)

    return {
        year: parts.year,
        month: parts.month,
        day: parts.day,
        hour: parts.hour % 24,
        minute: parts.minute,
        second: parts.second,
        millisecond: ((ms % 1000) + 1000) % 1000,
    }
}

// How far the zone is ahead of UTC at an instant, in ms.
const offsetAt = (ms, timezone) => {
    let p = zoneParts(ms, timezone)

    return Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute, p.second) - Math.floor(ms / 1000) * 1000
}

// The instant at which a zone's wall clock reads the given time. The offset that applies
// depends on the instant being looked for, so it is found by iterating: guess with the
// offset at the wall time read as UTC, then again with the offset at the guess. That is
// right on both sides of a DST change. A wall time that does not exist (the hour skipped
// when the clocks go forward) lands an hour before the change; one that happens twice (the
// hour repeated when they go back) takes the first of the two.
export const zoneInstant = ({ year, month, day, hour = 0, minute = 0, second = 0, millisecond = 0 }, timezone) => {
    let wall = Date.UTC(year, month - 1, day, hour, minute, second, millisecond)
    let guess = wall - offsetAt(wall, timezone)

    return new Date(wall - offsetAt(guess, timezone))
}

const parts = (wall) => ({
    year: wall.year(),
    month: wall.month() + 1,
    day: wall.date(),
    hour: wall.hour(),
    minute: wall.minute(),
    second: wall.second(),
    millisecond: wall.millisecond(),
})

export const zoneOf = (timezone) => {
    let zone = validZone(timezone)

    if (!zone) {
        return {
            zone: null,
            now: () => dayjs(),
            read: (value) => dayjs(value),
            write: (wall) => wall.toISOString(),
            start: (wall) => wall.startOf('day').toISOString(),
            end: (wall) => wall.endOf('day').toISOString(),
        }
    }

    return {
        zone,

        // Now, as the wall clock in the zone.
        now () {
            return this.read(Date.now())
        },

        // An instant (ISO string, Date or ms) as the wall clock in the zone; an invalid one stays invalid.
        read (value) {
            let ms = dayjs(value).valueOf()
            if (Number.isNaN(ms)) return dayjs(NaN)

            let p = zoneParts(ms, zone)

            return dayjs(new Date(p.year, p.month - 1, p.day, p.hour, p.minute, p.second, p.millisecond))
        },

        // A wall clock in the zone, exactly as it reads, as an ISO UTC string.
        write (wall) {
            return zoneInstant(parts(wall), zone).toISOString()
        },

        // The first and last millisecond of the wall clock's day in the zone.
        start (wall) {
            return zoneInstant({ ...parts(wall), hour: 0, minute: 0, second: 0, millisecond: 0 }, zone).toISOString()
        },

        end (wall) {
            return zoneInstant({ ...parts(wall), hour: 23, minute: 59, second: 59, millisecond: 999 }, zone).toISOString()
        },
    }
}
