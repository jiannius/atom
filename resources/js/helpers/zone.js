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
const DAY = 86400000
const formatters = new Map()
const warned = new Set()

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

// The instant at which a zone's wall clock reads the given time. The offset that applies depends on the instant being looked
// for, and a change of offset can be near it, so it is not guessed from one place: the zone's offset a day before the wall time
// and a day after it (read as UTC) each give a candidate, and a candidate counts if the zone's clock really reads that time at it.
//   - one counts: that is the instant (the usual case, both offsets being the same).
//   - both count (the hour repeated when the clocks go back): the earlier, the first time the clock reads it.
//   - neither counts (the hour skipped when the clocks go forward, or a skipped midnight such as Santiago's on 6 Sep 2026): the
//     later, which is the same wall time read with the offset the clock has gone to, so a skipped 00:00 is the 01:00 that follows
//     the change and a day never starts before it begins.
export const zoneInstant = ({ year, month, day, hour = 0, minute = 0, second = 0, millisecond = 0 }, timezone) => {
    let wall = Date.UTC(year, month - 1, day, hour, minute, second, millisecond)
    let asked = new Date(wall)
    let reads = (ms) => {
        let p = zoneParts(ms, timezone)

        return p.year === asked.getUTCFullYear() && p.month === asked.getUTCMonth() + 1 && p.day === asked.getUTCDate()
            && p.hour === asked.getUTCHours() && p.minute === asked.getUTCMinutes() && p.second === asked.getUTCSeconds()
    }

    let candidates = [...new Set([wall - offsetAt(wall - DAY, timezone), wall - offsetAt(wall + DAY, timezone)])]
    let matches = candidates.filter(reads)

    return new Date(matches.length ? Math.min(...matches) : Math.max(...candidates))
}

// A date or a date and time with no offset or Z: YYYY-MM-DD, or YYYY-MM-DD[T ]HH:mm[:ss[.SSS]]
const BARE = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d+))?)?)?$/

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

    // a typo in the prop is the host's to see once, not a throw in a picker
    if (!zone && typeof timezone === 'string' && timezone.trim() && !warned.has(timezone)) {
        warned.add(timezone)
        console.warn(`[atom] date-picker: "${timezone}" is not a timezone this browser knows; the picker uses the browser's zone.`)
    }

    if (!zone) {
        return {
            zone: null,
            now: () => dayjs(),
            read: (value) => dayjs(value),
            instant: (value) => dayjs(value).toISOString(),
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

        // An instant (ISO string, Date or ms) as the wall clock in the zone; an invalid one stays invalid. A string with no offset ("2026-09-01",
        // "2026-09-01T09:30:00") is not an instant: it is a time on the clock in the zone, read as it says.
        read (value) {
            let bare = typeof value === 'string' ? value.trim().match(BARE) : null

            if (bare) {
                let [, y, m, d, h = 0, mi = 0, s = 0, ms = 0] = bare

                return dayjs(new Date(+y, m - 1, +d, +h, +mi, +s, +String(ms).slice(0, 3).padEnd(3, "0")))
            }

            let ms = dayjs(value).valueOf()
            if (Number.isNaN(ms)) return dayjs(NaN)

            let p = zoneParts(ms, zone)

            return dayjs(new Date(p.year, p.month - 1, p.day, p.hour, p.minute, p.second, p.millisecond))
        },

        // The instant a stored value stands for, as an ISO UTC string: a string with no offset is a time in the zone.
        instant (value) {
            return typeof value === 'string' && BARE.test(value.trim()) ? this.write(this.read(value)) : dayjs(value).toISOString()
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
