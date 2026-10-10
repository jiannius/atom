import { test, expect } from '@playwright/test'
import { COMPACT_MINUTES, MIN_DRAWN_MINUTES, allDayRow, columnBlocks, packColumns, segmentOn, timeGrid } from '../../resources/js/alpinejs/calendar/grid.js'
import { addDays, minuteOf, nowAt } from '../../resources/js/alpinejs/calendar/dates.js'

// The time grid's layout is pure: items in, where each block sits in its day out. Positions are percentages of the
// day (top, height) and of the column (left, width), from the wall-clock minutes the server sends, so a daylight-saving
// day is just 24 rows of wall clock (see "a day the clocks change").

const bar = (id, start, end = null, extra = {}) => ({ id, title: String(id), start, end, allDay: true, color: null, icon: null, danger: false, href: null, ...extra })
const timed = (id, start, end = null, extra = {}) => ({ ...bar(id, start, end), allDay: false, ...extra })

const blocks = (items, date = '2026-10-09', options = {}) => columnBlocks(items, date, options)
const block = (list, id) => list.find((b) => b.id === id)

const week = Array.from({ length: 7 }, (_, i) => ({ date: addDays('2026-10-05', i), weekend: i > 4, outside: false }))

test.describe('a block\'s place in the day', () => {
    test('top and height are the start and the length as a share of 24 hours', () => {
        const [a] = blocks([timed('a', '2026-10-09T09:30', '2026-10-09T10:30')])

        expect(a.top).toBeCloseTo(9.5 / 24 * 100, 3)
        expect(a.height).toBeCloseTo(1 / 24 * 100, 3)
        expect(a.left).toBe(0)
        expect(a.width).toBe(100)
    })

    test('an item with no end is one slot long', () => {
        const [a] = blocks([timed('a', '2026-10-09T09:00')])
        const [b] = blocks([timed('b', '2026-10-09T09:00')], '2026-10-09', { slotDuration: 60 })

        expect(a.height).toBeCloseTo(30 / 1440 * 100, 3)
        expect(b.height).toBeCloseTo(60 / 1440 * 100, 3)
    })

    test('an item with no end near midnight stops at the end of the day instead of running past it', () => {
        const [a] = blocks([timed('a', '2026-10-09T23:50')])

        expect(a.top + a.height).toBeCloseTo(100, 3)
        expect(a.height).toBeCloseTo(10 / 1440 * 100, 3)
    })

    test('a timed end is exclusive, so an item that ends at midnight stays on its day', () => {
        const mine = blocks([timed('a', '2026-10-09T22:00', '2026-10-10T00:00')])
        const next = blocks([timed('a', '2026-10-09T22:00', '2026-10-10T00:00')], '2026-10-10')

        expect(mine).toHaveLength(1)
        expect(mine[0].top + mine[0].height).toBeCloseTo(100, 3)
        expect(next).toHaveLength(0)
    })

    test('an item that crosses midnight is a segment on each day, and says which edge carries on', () => {
        const items = [timed('night', '2026-10-09T22:00', '2026-10-10T02:00')]
        const [first] = blocks(items, '2026-10-09')
        const [second] = blocks(items, '2026-10-10')

        expect(first).toMatchObject({ before: false, after: true, startMinute: 22 * 60, endMinute: 1440 })
        expect(second).toMatchObject({ before: true, after: false, startMinute: 0, endMinute: 120 })
        expect(first.key).not.toBe(second.key)
    })

    test('an item over three days fills the middle one', () => {
        const [middle] = blocks([timed('long', '2026-10-08T20:00', '2026-10-10T08:00')], '2026-10-09')

        expect(middle).toMatchObject({ before: true, after: true, top: 0, height: 100 })
    })

    test('an all-day item has no block, and an item on another day none either', () => {
        expect(segmentOn(bar('a', '2026-10-09'), '2026-10-09')).toBeNull()
        expect(blocks([timed('a', '2026-10-08T10:00', '2026-10-08T11:00')])).toHaveLength(0)
    })

    test('a short item prints on one line, a longer one gets the second', () => {
        const list = blocks([
            timed('short', '2026-10-09T09:00', '2026-10-09T09:30'),
            timed('long', '2026-10-09T11:00', '2026-10-09T12:00'),
            timed('open', '2026-10-09T14:00'),
        ])

        expect(block(list, 'short').compact).toBe(true)
        expect(block(list, 'open').compact).toBe(true)
        expect(block(list, 'long').compact).toBe(false)
        expect(COMPACT_MINUTES).toBeGreaterThan(30)
    })
})

test.describe('side-by-side lanes', () => {
    test('two overlapping items are two columns of half the width', () => {
        const list = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('b', '2026-10-09T09:30', '2026-10-09T10:30'),
        ])

        expect(block(list, 'a')).toMatchObject({ left: 0, width: 50, column: 0, columns: 2 })
        expect(block(list, 'b')).toMatchObject({ left: 50, width: 50, column: 1, columns: 2 })
    })

    test('items that only touch do not share a lane', () => {
        const list = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('b', '2026-10-09T10:00', '2026-10-09T11:00'),
        ])

        expect(list.map((b) => [b.left, b.width])).toEqual([[0, 100], [0, 100]])
    })

    test('A-B and B-C, with A and C apart, are one cluster of two columns and C goes under A', () => {
        const list = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('b', '2026-10-09T09:30', '2026-10-09T10:30'),
            timed('c', '2026-10-09T10:00', '2026-10-09T11:00'),
        ])

        expect(block(list, 'a')).toMatchObject({ column: 0, columns: 2, width: 50 })
        expect(block(list, 'b')).toMatchObject({ column: 1, columns: 2, width: 50 })
        expect(block(list, 'c')).toMatchObject({ column: 0, columns: 2, width: 50 })
    })

    test('three that overlap are three columns, and the widths stay put across the whole cluster', () => {
        const list = blocks([
            timed('a', '2026-10-09T15:00', '2026-10-09T16:00'),
            timed('b', '2026-10-09T15:00', '2026-10-09T16:00'),
            timed('c', '2026-10-09T15:30', '2026-10-09T16:30'),
        ])

        expect(list.map((b) => b.columns)).toEqual([3, 3, 3])
        expect(list.map((b) => b.width)).toEqual([33.3333, 33.3333, 33.3333])
        expect(list.map((b) => b.left)).toEqual([0, 33.3333, 66.6667])
    })

    test('a lone item after a cluster is full width again', () => {
        const list = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('b', '2026-10-09T09:30', '2026-10-09T10:30'),
            timed('solo', '2026-10-09T13:00', '2026-10-09T14:00'),
        ])

        expect(block(list, 'solo')).toMatchObject({ left: 0, width: 100, columns: 1 })
    })

    test('the longer item takes the first column when two start together', () => {
        const list = blocks([
            timed('short', '2026-10-09T09:00', '2026-10-09T09:30'),
            timed('long', '2026-10-09T09:00', '2026-10-09T11:00'),
        ])

        expect(block(list, 'long').column).toBe(0)
        expect(block(list, 'short').column).toBe(1)
    })

    test('two short items close together do not sit on top of each other: lanes follow what is drawn', () => {
        // 10 minutes each, 15 apart: logically apart, but each is drawn 20px (about 25 minutes) tall
        const list = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T09:10'),
            timed('b', '2026-10-09T09:15', '2026-10-09T09:25'),
        ])

        expect(list.map((b) => b.columns)).toEqual([2, 2])
        expect(MIN_DRAWN_MINUTES).toBeGreaterThanOrEqual(25)

        // far enough apart to clear each other
        const apart = blocks([
            timed('a', '2026-10-09T09:00', '2026-10-09T09:10'),
            timed('b', '2026-10-09T09:30', '2026-10-09T09:40'),
        ])

        expect(apart.map((b) => b.columns)).toEqual([1, 1])
    })

    test('blocks come back in start order, ties in the order the host gave them', () => {
        const list = blocks([
            timed('late', '2026-10-09T15:00', '2026-10-09T16:00'),
            timed('tie-1', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('tie-2', '2026-10-09T09:00', '2026-10-09T10:00'),
        ])

        expect(list.map((b) => b.id)).toEqual(['tie-1', 'tie-2', 'late'])
    })

    test('packColumns writes the column and the cluster size on what it is given', () => {
        const segments = [
            { index: 0, start: 0, end: 60, drawnEnd: 60 },
            { index: 1, start: 30, end: 90, drawnEnd: 90 },
        ]

        packColumns(segments)

        expect(segments.map((s) => [s.column, s.columns])).toEqual([[0, 2], [1, 2]])
    })
})

test.describe('the all-day row', () => {
    test('puts all-day items in lanes over the shown days, and leaves timed ones out', () => {
        const row = allDayRow(week, [
            bar('launch', '2026-10-05', '2026-10-09'),
            bar('holiday', '2026-10-07'),
            timed('meeting', '2026-10-07T10:00', '2026-10-07T11:00'),
        ])

        expect(row.chips.map((c) => [c.id, c.col, c.span, c.row])).toEqual([['launch', 1, 5, 1], ['holiday', 3, 1, 2]])
        expect(row.rows).toBe(2)
    })

    test('a bar that starts before the shown days is cut at the edge and says it carries on', () => {
        const row = allDayRow(week, [bar('long', '2026-10-01', '2026-10-06'), bar('after', '2026-10-10', '2026-10-14')])

        expect(row.chips.find((c) => c.id === 'long')).toMatchObject({ col: 1, span: 2, before: true, after: false })
        expect(row.chips.find((c) => c.id === 'after')).toMatchObject({ col: 6, span: 2, before: false, after: true })
    })

    test('shows `max` lanes, then "+N more" with the exact number left on that day', () => {
        const items = ['a', 'b', 'c', 'd', 'e'].map((id) => bar(id, '2026-10-07'))
        const row = allDayRow(week, [...items, bar('over', '2026-10-06', '2026-10-07')], { max: 3 })

        expect(row.chips).toHaveLength(3)
        expect(row.more).toEqual([{ key: '2026-10-05:more:2026-10-07', date: '2026-10-07', col: 3, row: 4, count: 3 }])
        expect(row.rows).toBe(4)
    })

    test('a limit of null shows every lane', () => {
        const row = allDayRow(week, ['a', 'b', 'c', 'd', 'e'].map((id) => bar(id, '2026-10-07')), { max: null })

        expect(row.chips).toHaveLength(5)
        expect(row.more).toEqual([])
        expect(row.rows).toBe(5)
    })

    test('a day view has one column, and a multi-day bar is one column wide in it', () => {
        const row = allDayRow([{ date: '2026-10-07' }], [bar('long', '2026-10-05', '2026-10-09')])

        expect(row.chips[0]).toMatchObject({ col: 1, span: 1, before: true, after: true })
    })

    test('with nothing all-day there are no rows', () => {
        expect(allDayRow(week, [timed('a', '2026-10-07T10:00', '2026-10-07T11:00')])).toEqual({ chips: [], more: [], rows: 0 })
    })
})

test.describe('the grid', () => {
    test('a column per day, marking today, the past and the weekend, with every item counted', () => {
        const grid = timeGrid(week, [bar('a', '2026-10-09'), timed('b', '2026-10-09T10:00', '2026-10-09T11:00')], { today: '2026-10-09' })

        expect(grid.columns).toHaveLength(7)
        expect(grid.columns[4]).toMatchObject({ date: '2026-10-09', today: true, past: false, count: 2 })
        expect(grid.columns[3]).toMatchObject({ today: false, past: true, count: 0 })
        expect(grid.columns[5].weekend).toBe(true)
        expect(grid.columns[4].blocks).toHaveLength(1)
        expect(grid.allDay.chips).toHaveLength(1)
    })
})

test.describe('a day the clocks change', () => {
    // A viewer in Los Angeles. The server sends wall-clock strings, so a day is 24 rows whatever the clocks did.
    // 2026-03-08: 02:00 jumps to 03:00. 2026-11-01: 01:00 to 02:00 happens twice.

    test('spring forward: nothing sits in the hour that does not exist, and an item over the gap is as tall as the wall clock', () => {
        // 01:30 PST to 03:30 PDT is one real hour; on the wall clock it is two
        const list = blocks([
            timed('across', '2026-03-08T01:30', '2026-03-08T03:30'),
            timed('after', '2026-03-08T03:00', '2026-03-08T04:00'),
        ], '2026-03-08')

        const across = block(list, 'across')

        expect(across.top).toBeCloseTo(90 / 1440 * 100, 3)
        expect(across.height).toBeCloseTo(120 / 1440 * 100, 3)
        // the 02:00 row is empty: no block starts in it
        expect(list.filter((b) => b.startMinute >= 120 && b.startMinute < 180)).toEqual([])
        // the two overlap on the wall clock, so they are side by side
        expect(across.columns).toBe(2)
    })

    test('fall back: the repeated 01:30 is one row, and items from both passes share it side by side', () => {
        const list = blocks([
            timed('first', '2026-11-01T01:30', '2026-11-01T01:55'),
            timed('second', '2026-11-01T01:30', '2026-11-01T02:00'),
            timed('noon', '2026-11-01T12:00', '2026-11-01T13:00'),
        ], '2026-11-01')

        expect(block(list, 'first').top).toBe(block(list, 'second').top)
        expect(block(list, 'first').columns).toBe(2)
        expect(block(list, 'noon').top).toBeCloseTo(50, 3)
    })

    test('fall back: an item from 01:30 PDT to 01:30 PST has no end on the wall clock and takes one slot', () => {
        // the data layer drops an end that is not after the start, so the grid gets none
        const [a] = blocks([timed('hour', '2026-11-01T01:30', null)], '2026-11-01')

        expect(a.height).toBeCloseTo(30 / 1440 * 100, 3)
    })

    test('the grid has 24 hours on either day, whatever the clocks did', () => {
        for (const date of ['2026-03-08', '2026-11-01']) {
            const grid = timeGrid([{ date }], [timed('a', date + 'T12:00', date + 'T13:00')])

            expect(grid.columns[0].blocks[0].top).toBeCloseTo(50, 3)
        }
    })
})

test.describe('the clock', () => {
    test('carries the server\'s clock on by the time elapsed since it was read', () => {
        expect(nowAt('2026-10-09T10:30:00', 0)).toBe('2026-10-09T10:30')
        expect(nowAt('2026-10-09T10:30:00', 29000)).toBe('2026-10-09T10:30')
        expect(nowAt('2026-10-09T10:30:45', 15000)).toBe('2026-10-09T10:31')
        expect(nowAt('2026-10-09T10:30:00', 3 * 3600 * 1000)).toBe('2026-10-09T13:30')
    })

    test('rolls over midnight into the next date', () => {
        expect(nowAt('2026-10-09T23:59:30', 45000)).toBe('2026-10-10T00:00')
        expect(nowAt('2026-12-31T23:30:00', 3600 * 1000)).toBe('2027-01-01T00:30')
    })

    test('has nothing to say for a seed that is not a moment, or an elapsed time that is not a number', () => {
        expect(nowAt('', 0)).toBe('')
        expect(nowAt('2026-13-40T10:30:00', 0)).toBe('')
        expect(nowAt('2026-10-09T10:30:00', Number.NaN)).toBe('')
    })

    test('reads minutes after midnight off a moment', () => {
        expect(minuteOf('2026-10-09T10:30')).toBe(630)
        expect(minuteOf('2026-10-09')).toBe(0)
    })
})
