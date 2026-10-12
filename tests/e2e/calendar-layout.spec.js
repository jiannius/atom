import { test, expect } from '@playwright/test'
import { dayEntries, isBar, isPlaceable, monthLayout, span } from '../../resources/js/alpinejs/calendar/layout.js'
import { addDays } from '../../resources/js/alpinejs/calendar/dates.js'

// The month layout is pure: window days and items in, chip placement out. October 2026 under a
// Monday start is the window 2026-09-28 to 2026-11-08, so week 1 is Mon 09-28 to Sun 10-04 and
// week 2 is Mon 10-05 to Sun 10-11.

const days = Array.from({ length: 42 }, (_, i) => {
    const date = addDays('2026-09-28', i)

    return { date, weekend: i % 7 > 4, outside: date < '2026-10-01' || date > '2026-10-31' }
})

const bar = (id, start, end = null, extra = {}) => ({ id, title: String(id), start, end, allDay: true, color: null, icon: null, danger: false, href: null, ...extra })
const timed = (id, start, end = null, extra = {}) => ({ ...bar(id, start, end), allDay: false, ...extra })

const layout = (items, options = {}) => monthLayout(days, items, { max: 3, today: '2026-10-09', ...options })
const chip = (week, id) => week.chips.find((c) => c.id === id)

test.describe('spans', () => {
    test('an item with no end is one day, and an all-day end is inclusive', () => {
        expect(span(bar('a', '2026-10-05'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })
        expect(span(bar('a', '2026-10-05', '2026-10-07'))).toEqual({ first: '2026-10-05', last: '2026-10-07' })
    })

    test('a timed end is exclusive: ending at midnight does not reach into the next day', () => {
        expect(span(timed('a', '2026-10-05T22:00', '2026-10-06T00:00'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })
        expect(span(timed('a', '2026-10-05T22:00', '2026-10-06T00:30'))).toEqual({ first: '2026-10-05', last: '2026-10-06' })
    })

    test('a timed item with no end stays on its day', () => {
        expect(span(timed('a', '2026-10-05T09:00'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })
    })

    test('an end before the start cannot reach back', () => {
        expect(span(bar('a', '2026-10-05', '2026-10-01'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })
    })

    test('all-day and multi-day items are bars, a single-day timed item is not', () => {
        expect(isBar(bar('a', '2026-10-05'))).toBe(true)
        expect(isBar(timed('a', '2026-10-05T09:00', '2026-10-05T10:00'))).toBe(false)
        expect(isBar(timed('a', '2026-10-05T22:00', '2026-10-06T02:00'))).toBe(true)
        expect(isBar(timed('a', '2026-10-05T22:00', '2026-10-06T00:00'))).toBe(false)
    })
})

test.describe('weeks and days', () => {
    test('six weeks of seven days, keyed by their first date', () => {
        const weeks = layout([])

        expect(weeks).toHaveLength(6)
        expect(weeks.map((w) => w.key)).toEqual(['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'])
        expect(weeks.every((w) => w.days.length === 7)).toBe(true)
    })

    test('marks today, the past, the weekend and days outside the month', () => {
        const [first, second] = layout([])

        expect(first.days[0]).toMatchObject({ date: '2026-09-28', number: 28, col: 1, outside: true, past: true, today: false, weekend: false })
        expect(first.days[5]).toMatchObject({ date: '2026-10-03', weekend: true, outside: false })
        expect(second.days[4]).toMatchObject({ date: '2026-10-09', today: true, past: false })
        expect(second.days[5].past).toBe(false)
    })

    test('without a today nothing is past or today', () => {
        const [first] = layout([], { today: null })

        expect(first.days.some((d) => d.today || d.past)).toBe(false)
    })
})

test.describe('bars and lanes', () => {
    test('two overlapping bars take two lanes, one under the other', () => {
        const [, week] = layout([bar('a', '2026-10-05', '2026-10-07'), bar('b', '2026-10-06', '2026-10-08')])

        expect(chip(week, 'a')).toMatchObject({ kind: 'bar', col: 1, span: 3, row: 2 })
        expect(chip(week, 'b')).toMatchObject({ kind: 'bar', col: 2, span: 3, row: 3 })
    })

    test('a chain where the first and last never meet shares a lane', () => {
        // A Mon-Tue, B Tue-Wed, C Wed-Thu: A and C do not overlap, so C drops back into A's lane
        const [, week] = layout([
            bar('a', '2026-10-05', '2026-10-06'),
            bar('b', '2026-10-06', '2026-10-07'),
            bar('c', '2026-10-07', '2026-10-08'),
        ])

        expect(chip(week, 'a').row).toBe(2)
        expect(chip(week, 'b').row).toBe(3)
        expect(chip(week, 'c').row).toBe(2)
    })

    test('a longer bar that starts the same day goes first', () => {
        const [, week] = layout([bar('short', '2026-10-05'), bar('long', '2026-10-05', '2026-10-09')])

        expect(chip(week, 'long').row).toBe(2)
        expect(chip(week, 'short').row).toBe(3)
    })

    test('an all-day bar goes before a timed one that crosses midnight, on the same start and length', () => {
        const [, week] = layout([
            timed('crossing', '2026-10-05T22:00', '2026-10-06T02:00'),
            bar('allday', '2026-10-05', '2026-10-06'),
        ])

        expect(chip(week, 'allday').row).toBe(2)
        expect(chip(week, 'crossing').row).toBe(3)
    })

    test('a bar is split at the week row, and each half says where it continues', () => {
        // Fri 10-02 to Tue 10-06
        const [first, second] = layout([bar('a', '2026-10-02', '2026-10-06')])

        expect(chip(first, 'a')).toMatchObject({ col: 5, span: 3, before: false, after: true, key: '2026-09-28:a' })
        expect(chip(second, 'a')).toMatchObject({ col: 1, span: 2, before: true, after: false, key: '2026-10-05:a' })
    })

    test('a bar that starts before the window and ends after it covers every week', () => {
        const weeks = layout([bar('a', '2026-09-01', '2026-12-31')])

        expect(weeks.every((w) => chip(w, 'a')?.span === 7)).toBe(true)
        expect(chip(weeks[0], 'a')).toMatchObject({ before: true, after: true })
    })

    test('an item outside the window is left out', () => {
        const weeks = layout([bar('a', '2026-12-01'), bar('b', '2026-08-01', '2026-09-27')])

        expect(weeks.flatMap((w) => w.chips)).toEqual([])
    })

    test('a timed item that crosses midnight is a bar across both days', () => {
        const [, week] = layout([timed('a', '2026-10-05T22:00', '2026-10-06T02:00')])

        expect(chip(week, 'a')).toMatchObject({ kind: 'bar', col: 1, span: 2 })
    })

    test('a null end draws one day', () => {
        const [, week] = layout([bar('a', '2026-10-07', null), timed('b', '2026-10-08T09:00', null)])

        expect(chip(week, 'a')).toMatchObject({ kind: 'bar', col: 3, span: 1 })
        expect(chip(week, 'b')).toMatchObject({ kind: 'dot', col: 4, span: 1 })
    })
})

test.describe('dots', () => {
    test('a single-day timed item is a dot row, ordered by time and then by the order given', () => {
        const [, week] = layout([
            timed('late', '2026-10-05T15:00', '2026-10-05T16:00'),
            timed('early', '2026-10-05T09:00', '2026-10-05T10:00'),
            timed('tie-1', '2026-10-05T12:00'),
            timed('tie-2', '2026-10-05T12:00'),
        ])

        // max 3: the first three rows show, the fourth item is behind "+1 more"
        expect(['early', 'tie-1', 'tie-2', 'late'].map((id) => chip(week, id)?.row)).toEqual([2, 3, 4, undefined])
        expect(week.more.map((m) => m.count)).toEqual([1])
    })

    test('dots go under the bars on their day', () => {
        const [, week] = layout([bar('bar', '2026-10-05'), timed('dot', '2026-10-05T09:00')])

        expect(chip(week, 'bar').row).toBe(2)
        expect(chip(week, 'dot').row).toBe(3)
    })

    test('a dot fills a lane a bar leaves free on its own day', () => {
        // x runs Mon-Wed in lane 0 and y, Monday only, drops to lane 1. On Tuesday lane 1 is free, so the
        // dot takes it instead of going below every lane
        const [, week] = layout([
            bar('x', '2026-10-05', '2026-10-07'),
            bar('y', '2026-10-05'),
            timed('dot', '2026-10-06T09:00'),
        ])

        expect(chip(week, 'x').row).toBe(2)
        expect(chip(week, 'y').row).toBe(3)
        expect(chip(week, 'dot')).toMatchObject({ col: 2, row: 3 })
    })
})

test.describe('+N more', () => {
    const five = Array.from({ length: 5 }, (_, i) => timed('t' + i, `2026-10-05T0${i + 1}:00`))

    test('shows max rows and counts the rest exactly', () => {
        const [, week] = layout(five)

        expect(week.chips.map((c) => c.id)).toEqual(['t0', 't1', 't2'])
        expect(week.more).toEqual([{ key: '2026-10-05:more:2026-10-05', date: '2026-10-05', col: 1, row: 5, count: 2 }])
        expect(week.days[0].count).toBe(5)
    })

    test('exactly max items need no more row, one over needs one that says 1', () => {
        expect(layout(five.slice(0, 3))[1].more).toEqual([])
        expect(layout(five.slice(0, 4))[1].more[0].count).toBe(1)
    })

    test('a hidden bar counts on every day it covers', () => {
        // lanes 0-2 are held by three bars across Mon-Wed, so a fourth bar (Tue-Thu) lands in lane 3: hidden on
        // Tue, Wed and Thu, and the dot on Thursday still has a free row
        const [, week] = layout([
            bar('a', '2026-10-05', '2026-10-07'),
            bar('b', '2026-10-05', '2026-10-07'),
            bar('c', '2026-10-05', '2026-10-07'),
            bar('d', '2026-10-06', '2026-10-08'),
            timed('thu', '2026-10-08T09:00'),
        ])

        expect(chip(week, 'd')).toBeUndefined()
        expect(chip(week, 'thu')).toMatchObject({ kind: 'dot', row: 2 })
        expect(week.more.map((m) => [m.date, m.count])).toEqual([['2026-10-06', 1], ['2026-10-07', 1], ['2026-10-08', 1]])
        expect(week.days[3].count).toBe(2)
    })

    test('max null shows every row and never a more row', () => {
        const [, week] = layout(five, { max: null })

        expect(week.chips).toHaveLength(5)
        expect(week.more).toEqual([])
    })

    test('max 1 shows one row, then the rest as more in the row after', () => {
        const [, week] = layout(five.slice(0, 3), { max: 1 })

        expect(week.chips).toHaveLength(1)
        expect(week.more[0]).toMatchObject({ row: 3, count: 2 })
    })
})

test.describe('day lists', () => {
    test('lists every item on a date, bars first, then timed by start', () => {
        const items = [
            timed('lunch', '2026-10-05T12:00', '2026-10-05T13:00'),
            bar('span', '2026-10-04', '2026-10-06'),
            timed('breakfast', '2026-10-05T08:00', '2026-10-05T09:00'),
            bar('allday', '2026-10-05'),
            bar('elsewhere', '2026-10-09'),
        ]

        expect(dayEntries(items, '2026-10-05').map((i) => i.id)).toEqual(['span', 'allday', 'breakfast', 'lunch'])
        expect(dayEntries(items, '2026-10-06').map((i) => i.id)).toEqual(['span'])
        expect(dayEntries(items, '2026-11-01')).toEqual([])
    })

    test('a day carries up to four items for the narrow layout and the rest as a count', () => {
        const items = Array.from({ length: 6 }, (_, i) => timed('t' + i, `2026-10-05T0${i}:00`))
        const [, week] = layout(items)

        expect(week.days[0].dots).toHaveLength(4)
        expect(week.days[0].extra).toBe(2)
        expect(week.days[1].extra).toBe(0)
    })
})

test.describe('an item whose dates do not parse', () => {
    const junk = [
        bar('no-start', null),
        bar('text-start', 'next tuesday'),
        bar('missing-start', undefined),
        bar('impossible', '2026-02-30'),
        bar('number', 1791500000),
        timed('year-10000', '10000-01-01T09:00'),
        // these sort inside the window as text, which is what a layout that compared strings would put on a day
        bar('day-32', '2026-10-32'),
        timed('day-32-timed', '2026-10-32T09:00', '2026-10-33T10:00'),
        bar('month-0', '2026-00-10'),
    ]

    test('is not placeable, and a start that exists is', () => {
        for (const item of junk) expect(isPlaceable(item), String(item.id)).toBe(false)

        expect(isPlaceable(bar('ok', '2026-10-05'))).toBe(true)
        expect(isPlaceable(timed('ok', '2026-10-05T09:00'))).toBe(true)
        expect(isPlaceable(null)).toBe(false)
    })

    test('is left out of the month, and the others are laid out as if it had not been sent', () => {
        const good = [bar('a', '2026-10-05', '2026-10-07'), timed('b', '2026-10-06T09:00', '2026-10-06T10:00')]
        const clean = layout(good)
        const dirty = layout([junk[0], good[0], junk[1], junk[2], good[1], junk[3], junk[4], junk[5], junk[6], junk[7]])

        expect(dirty.map((week) => week.chips.map((c) => [c.id, c.col, c.span, c.row]))).toEqual(clean.map((week) => week.chips.map((c) => [c.id, c.col, c.span, c.row])))
        expect(dirty.map((week) => week.days.map((d) => d.count))).toEqual(clean.map((week) => week.days.map((d) => d.count)))
    })

    test('is left out of a day', () => {
        expect(dayEntries([...junk, bar('a', '2026-10-05')], '2026-10-05').map((i) => i.id)).toEqual(['a'])
        expect(dayEntries([...junk, bar('a', '2026-10-05')], '2026-10-32')).toEqual([])
    })

    test('an end that is not a date is no end: the item is its start day, as on the server', () => {
        expect(span(bar('a', '2026-10-05', 'soon'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })
        expect(span(timed('a', '2026-10-05T09:00', 'soon'))).toEqual({ first: '2026-10-05', last: '2026-10-05' })

        const [week] = layout([bar('a', '2026-10-01', 'soon')])

        expect(chip(week, 'a')).toMatchObject({ span: 1 })
    })

    test('a preview over a bad item does not throw', () => {
        expect(() => layout(junk, { preview: { kind: 'move', id: 'no-start', start: '2026-10-05', end: null, allDay: true } })).not.toThrow()
    })
})
