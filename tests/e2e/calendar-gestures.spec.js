import { test, expect } from '@playwright/test'
import { addMinutes, dayCell, hasMoved, indexAtX, indexAtY, minuteAt, momentAt, selectionRange } from '../../resources/js/alpinejs/calendar/gestures.js'
import { allDayShape } from '../../resources/js/alpinejs/calendar/grid.js'
import { addDays } from '../../resources/js/alpinejs/calendar/dates.js'

// What a pointer gesture works out from where it is, as pure functions (calendar/gestures.js): a point to a day or a slot, snapped, and made
// a range. Nothing here has a DOM; tests/e2e/calendar-select.spec.js drives the same in a browser.

const column = (left, width = 100) => ({ left, right: left + width })

test.describe('which box a point is in', () => {
    const columns = [column(0), column(100), column(200)]

    test('the box that holds it; a box starts at its left edge and ends before the next one', () => {
        expect(indexAtX(columns, 0)).toBe(0)
        expect(indexAtX(columns, 99.9)).toBe(0)
        expect(indexAtX(columns, 100)).toBe(1)
        expect(indexAtX(columns, 250)).toBe(2)
    })

    test('a point outside them all is on the nearest end, so a drag that leaves the grid sideways stays on its last column', () => {
        expect(indexAtX(columns, -50)).toBe(0)
        expect(indexAtX(columns, 900)).toBe(2)
        expect(indexAtX([], 10)).toBe(-1)
    })

    test('the same by height', () => {
        const weeks = [{ top: 0, bottom: 100 }, { top: 100, bottom: 200 }]

        expect(indexAtY(weeks, 150)).toBe(1)
        expect(indexAtY(weeks, -5)).toBe(0)
        expect(indexAtY(weeks, 500)).toBe(1)
        expect(indexAtY([], 1)).toBe(-1)
    })

    test('a month day is the week by its height and the seventh of its width', () => {
        const weeks = [{ top: 0, bottom: 100, left: 10, width: 700 }, { top: 100, bottom: 200, left: 10, width: 700 }]

        expect(dayCell(weeks, 10, 50)).toEqual({ row: 0, col: 0 })
        expect(dayCell(weeks, 109, 50)).toEqual({ row: 0, col: 0 })
        expect(dayCell(weeks, 110, 50)).toEqual({ row: 0, col: 1 })
        expect(dayCell(weeks, 709, 150)).toEqual({ row: 1, col: 6 })
        // left of the grid, and right of it, are its first and its last day
        expect(dayCell(weeks, -300, 150)).toEqual({ row: 1, col: 0 })
        expect(dayCell(weeks, 5000, 20)).toEqual({ row: 0, col: 6 })
    })
})

test.describe('the slot a point is in', () => {
    // a day 1440 px tall: a pixel is a minute
    const box = { top: 100, height: 1440 }

    test('snaps down to the start of the slot: 10:20 is in the 10:00 slot of half an hour', () => {
        expect(minuteAt(100 + 620, box, 30)).toBe(600)
        expect(minuteAt(100 + 629, box, 30)).toBe(600)
        expect(minuteAt(100 + 630, box, 30)).toBe(630)
    })

    test('follows the slot length', () => {
        expect(minuteAt(100 + 620, box, 15)).toBe(615)
        expect(minuteAt(100 + 620, box, 60)).toBe(600)
        expect(minuteAt(100 + 620, box, 5)).toBe(620)
    })

    test('a point above the day is its first slot and one under it is its last, whatever the length', () => {
        expect(minuteAt(-50, box, 30)).toBe(0)
        expect(minuteAt(100 + 1440, box, 30)).toBe(1410)
        expect(minuteAt(100 + 5000, box, 60)).toBe(1380)
        expect(minuteAt(100 + 1439, box, 15)).toBe(1425)
    })

    test('a column with no height gives the first slot instead of dividing by nothing', () => {
        expect(minuteAt(50, { top: 0, height: 0 }, 30)).toBe(0)
    })
})

test.describe('moments', () => {
    test('a moment is a date and the minute of its day', () => {
        expect(momentAt('2026-10-12', 0)).toBe('2026-10-12T00:00')
        expect(momentAt('2026-10-12', 605)).toBe('2026-10-12T10:05')
        expect(momentAt('2026-10-12', 1439)).toBe('2026-10-12T23:59')
    })

    test('minutes added to a moment carry into the next date at midnight, and across a month and a year', () => {
        expect(addMinutes('2026-10-12T10:00', 30)).toBe('2026-10-12T10:30')
        expect(addMinutes('2026-10-12T23:30', 30)).toBe('2026-10-13T00:00')
        expect(addMinutes('2026-10-31T23:45', 30)).toBe('2026-11-01T00:15')
        expect(addMinutes('2026-12-31T23:30', 60)).toBe('2027-01-01T00:30')
    })
})

test.describe('the range a selection covers', () => {
    test('days: from the one pressed to the one it is let go on, the last day included', () => {
        expect(selectionRange('2026-10-12', '2026-10-12')).toEqual({ start: '2026-10-12', end: '2026-10-12', allDay: true })
        expect(selectionRange('2026-10-12', '2026-10-15')).toEqual({ start: '2026-10-12', end: '2026-10-15', allDay: true })
        // across weeks, and across a month
        expect(selectionRange('2026-10-30', '2026-11-03')).toEqual({ start: '2026-10-30', end: '2026-11-03', allDay: true })
    })

    test('a drag backwards is the same range', () => {
        expect(selectionRange('2026-10-15', '2026-10-12')).toEqual({ start: '2026-10-12', end: '2026-10-15', allDay: true })
        expect(selectionRange('2026-10-12T11:00', '2026-10-12T09:00')).toEqual(selectionRange('2026-10-12T09:00', '2026-10-12T11:00'))
    })

    test('slots: the slot pressed is in it, and the one it is let go on, the end the minute after', () => {
        expect(selectionRange('2026-10-12T10:00', '2026-10-12T10:00')).toEqual({ start: '2026-10-12T10:00', end: '2026-10-12T10:30', allDay: false })
        expect(selectionRange('2026-10-12T09:00', '2026-10-12T11:00')).toEqual({ start: '2026-10-12T09:00', end: '2026-10-12T11:30', allDay: false })
        expect(selectionRange('2026-10-12T09:00', '2026-10-12T11:00', { slotDuration: 15 }).end).toBe('2026-10-12T11:15')
        expect(selectionRange('2026-10-12T09:00', '2026-10-12T11:00', { slotDuration: 60 }).end).toBe('2026-10-12T12:00')
    })

    test('across columns it is one range of time, from the earlier slot to the later, whichever column each is in', () => {
        expect(selectionRange('2026-10-12T10:00', '2026-10-14T11:00')).toEqual({ start: '2026-10-12T10:00', end: '2026-10-14T11:30', allDay: false })
        // a later column but an earlier time of day is still later
        expect(selectionRange('2026-10-12T15:00', '2026-10-13T09:00')).toEqual({ start: '2026-10-12T15:00', end: '2026-10-13T09:30', allDay: false })
        expect(selectionRange('2026-10-14T11:00', '2026-10-12T10:00')).toEqual({ start: '2026-10-12T10:00', end: '2026-10-14T11:30', allDay: false })
    })

    test('the last slot of the day ends at midnight, which is 00:00 of the next date', () => {
        expect(selectionRange('2026-10-12T23:30', '2026-10-12T23:30')).toEqual({ start: '2026-10-12T23:30', end: '2026-10-13T00:00', allDay: false })
        expect(selectionRange('2026-10-31T23:00', '2026-10-31T23:30').end).toBe('2026-11-01T00:00')
        expect(selectionRange('2026-10-12T23:00', '2026-10-12T23:00', { slotDuration: 60 }).end).toBe('2026-10-13T00:00')
    })
})

test.describe('how far is a drag', () => {
    test('a press that has not gone five pixels is a click', () => {
        expect(hasMoved({ x: 10, y: 10 }, { x: 10, y: 10 })).toBe(false)
        expect(hasMoved({ x: 10, y: 10 }, { x: 13, y: 13 })).toBe(false)
        expect(hasMoved({ x: 10, y: 10 }, { x: 15, y: 10 })).toBe(true)
        expect(hasMoved({ x: 10, y: 10 }, { x: 10, y: 4 })).toBe(true)
    })

    test('a longer slop is given for a finger', () => {
        expect(hasMoved({ x: 0, y: 0 }, { x: 8, y: 0 }, 10)).toBe(false)
        expect(hasMoved({ x: 0, y: 0 }, { x: 0, y: 10 }, 10)).toBe(true)
    })
})

test.describe('what a selection covers in the all-day row', () => {
    const days = Array.from({ length: 7 }, (_, i) => ({ date: addDays('2026-10-12', i), weekend: false, outside: false }))

    test('the days it covers, as shares of the row', () => {
        const shape = allDayShape(days, { kind: 'select', start: '2026-10-13', end: '2026-10-15', allDay: true })

        expect(shape).toMatchObject({ kind: 'select', before: false, after: false })
        expect(shape.left).toBeCloseTo(100 / 7, 4)
        expect(shape.width).toBeCloseTo(300 / 7, 4)
    })

    test('an item waiting for the host is there too, with its title; and a range that runs past the week is cut at its edges and says so', () => {
        const shape = allDayShape(days, { kind: 'pending', start: '2026-10-17', end: '2026-10-22', allDay: true, title: 'Creating...' })

        expect(shape).toMatchObject({ kind: 'pending', title: 'Creating...', before: false, after: true })
        expect(shape.left + shape.width).toBeCloseTo(100, 4)
        expect(allDayShape(days, { kind: 'select', start: '2026-10-08', end: '2026-10-13', allDay: true })).toMatchObject({ left: 0, before: true, after: false })
    })

    test('nothing for a timed range (that is the columns\'), another kind of gesture, no preview, or days that are not shown', () => {
        expect(allDayShape(days, { kind: 'select', start: '2026-10-13T10:00', end: '2026-10-13T11:00', allDay: false })).toBeNull()
        expect(allDayShape(days, { kind: 'move', id: 'a', start: '2026-10-13', end: '2026-10-13', allDay: true })).toBeNull()
        expect(allDayShape(days, null)).toBeNull()
        expect(allDayShape(days, { kind: 'select', start: '2026-11-13', end: '2026-11-13', allDay: true })).toBeNull()
        expect(allDayShape([], { kind: 'select', start: '2026-10-13', end: '2026-10-13', allDay: true })).toBeNull()
    })
})
