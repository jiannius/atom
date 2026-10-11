import { addDays, dateOf, minuteOf } from './dates.js'
import { DAY_MINUTES } from './grid.js'

// What a pointer gesture on <atom:calendar> works out from where it is, as pure functions: no DOM, no Alpine, no clock. The factory reads
// the boxes of the days and the columns and hands them in, so a point can be mapped to a day or a slot, snapped, and made a range in node.

// How far a pointer has to move, in px, before a press is a drag and not a click
export const DRAG_THRESHOLD = 5

// How long a finger has to stay where it is, in ms, before a touch is a gesture and not a scroll
export const LONG_PRESS = 500

const pad = (n) => String(n).padStart(2, '0')

const clamp = (value, low, high) => Math.min(Math.max(value, low), high)

// The index of the box whose width holds `x`, or the nearest end when it is outside all of them: a drag that leaves the grid
// sideways stays on its last column. `rects` are { left, right } in the order they are drawn.
export const indexAtX = (rects, x) => {
    if (!rects.length) return -1

    let found = rects.findIndex((rect) => x >= rect.left && x < rect.right)

    if (found !== -1) return found

    return x < rects[0].left ? 0 : rects.length - 1
}

// The same by height: the box whose `top` and `bottom` hold `y`, or the first or the last
export const indexAtY = (rects, y) => {
    if (!rects.length) return -1

    let found = rects.findIndex((rect) => y >= rect.top && y < rect.bottom)

    if (found !== -1) return found

    return y < rects[0].top ? 0 : rects.length - 1
}

// The minute of the day a point is at in a column's box, snapped down to the start of its slot: 10:20 is in the 10:00 slot of
// a half hour. `slotDuration` is a divisor of an hour, so the last slot ends at midnight.
export const minuteAt = (y, rect, slotDuration = 30) => {
    let share = rect.height > 0 ? (y - rect.top) / rect.height : 0
    let minute = clamp(Math.floor(share * DAY_MINUTES), 0, DAY_MINUTES - 1)

    return Math.floor(minute / slotDuration) * slotDuration
}

// A moment `YYYY-MM-DDTHH:mm`, and a moment that many minutes later (midnight carries into the next date)
export const momentAt = (date, minute) => date + 'T' + pad(Math.floor(minute / 60)) + ':' + pad(minute % 60)

export const addMinutes = (moment, minutes) => {
    let total = minuteOf(moment) + minutes
    let days = Math.floor(total / DAY_MINUTES)

    return momentAt(addDays(dateOf(moment), days), total - days * DAY_MINUTES)
}

// The day of a month grid's week under a point: the week by its height, the day of the week by sevenths of its width. `weeks` are
// the boxes of the weeks in order, and `count` the days in a week.
export const dayCell = (weeks, x, y, count = 7) => {
    let row = indexAtY(weeks, y)

    if (row === -1) return null

    let { left, width } = weeks[row]

    return { row, col: clamp(Math.floor(((x - left) / (width || 1)) * count), 0, count - 1) }
}

// Whether a pointer has gone far enough from where it was pressed to be dragging
export const hasMoved = (from, to, threshold = DRAG_THRESHOLD) => Math.hypot(to.x - from.x, to.y - from.y) >= threshold

// The range a selection covers, from where it was pressed (`anchor`) to where it is now (`current`), whichever is first. A date
// (`YYYY-MM-DD`) selects whole days, the last included: { start, end } are dates, `allDay`. A moment (`YYYY-MM-DDTHH:mm`, the start of
// a slot) selects whole slots, the one that holds `current` too: { start, end } are moments, the end the first minute after, and a range
// that runs on to midnight ends at 00:00 of the next date. Both ends are the viewer's wall clock, as the payload's items are.
export const selectionRange = (anchor, current, { slotDuration = 30 } = {}) => {
    let [first, last] = anchor <= current ? [anchor, current] : [current, anchor]

    if (!String(first).includes('T')) return { start: first, end: last, allDay: true }

    return { start: first, end: addMinutes(last, slotDuration), allDay: false }
}
