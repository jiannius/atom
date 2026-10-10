import { test, expect } from '@playwright/test'
import { CASCADE_MAX, CASCADE_STEP, columnBlocks, previewBlocks, timeGrid } from '../../resources/js/alpinejs/calendar/grid.js'
import { monthLayout } from '../../resources/js/alpinejs/calendar/layout.js'
import { EDGE_CONTRAST, contrast, DARK_GROUND, edgeColors, LIGHT_GROUND, parseHex } from '../../resources/js/alpinejs/calendar/colors.js'
import { addDays } from '../../resources/js/alpinejs/calendar/dates.js'

// The states a gesture draws (a selection, an item waiting for the host, the ghost of a move or a resize), the cascade way of laying
// out overlapping items, and the colours of a hex item's edge. All pure: items in, shapes out. The gestures that set the state are
// not wired yet; the shapes are what they will set, and tests/e2e/calendar-look.spec.js looks at them in a browser.

const bar = (id, start, end = null, extra = {}) => ({ id, title: String(id), start, end, allDay: true, color: null, icon: null, danger: false, href: null, ...extra })
const timed = (id, start, end = null, extra = {}) => ({ ...bar(id, start, end), allDay: false, ...extra })

const week = Array.from({ length: 7 }, (_, i) => ({ date: addDays('2026-10-05', i), weekend: i > 4, outside: false }))
const days = Array.from({ length: 42 }, (_, i) => ({ date: addDays('2026-09-28', i), weekend: i % 7 > 4, outside: false }))

test.describe('what a selection and a pending item draw in the time grid', () => {
    const items = [timed('design', '2026-10-09T10:00', '2026-10-09T11:30', { color: 'orange' })]

    test('a box where the range is, as a share of the day, in the whole column', () => {
        const [shape] = previewBlocks({ kind: 'select', start: '2026-10-09T10:00', end: '2026-10-09T11:30' }, items, '2026-10-09')

        expect(shape).toMatchObject({ kind: 'select', left: 0, width: 100 })
        expect(shape.top).toBeCloseTo(10 / 24 * 100, 3)
        expect(shape.height).toBeCloseTo(1.5 / 24 * 100, 3)
        // it is a plain box: nothing of any item is in it
        expect(shape.id).toBeUndefined()
    })

    test('a selection across midnight is a box in each of the two days, from and to the day\'s edge', () => {
        const preview = { kind: 'select', start: '2026-10-09T22:00', end: '2026-10-10T01:00' }
        const [first] = previewBlocks(preview, items, '2026-10-09')
        const [second] = previewBlocks(preview, items, '2026-10-10')

        expect(first.top + first.height).toBeCloseTo(100, 3)
        expect(first.after).toBe(true)
        expect(second.top).toBe(0)
        expect(second.before).toBe(true)
        expect(previewBlocks(preview, items, '2026-10-11')).toEqual([])
        // both keep the whole range for the label
        expect(second.start).toBe('2026-10-09T22:00')
        expect(second.end).toBe('2026-10-10T01:00')
    })

    test('an item waiting for the host is a box with its title', () => {
        const [shape] = previewBlocks({ kind: 'pending', start: '2026-10-09T13:30', end: '2026-10-09T14:30', title: 'New task' }, items, '2026-10-09')

        expect(shape).toMatchObject({ kind: 'pending', title: 'New task' })
    })

    test('nothing is drawn for no preview, an all-day one (the all-day row is not a time grid), or another day', () => {
        expect(previewBlocks(null, items, '2026-10-09')).toEqual([])
        expect(previewBlocks({ kind: 'select', start: '2026-10-09', end: '2026-10-09', allDay: true }, items, '2026-10-09')).toEqual([])
        expect(previewBlocks({ kind: 'select', start: '2026-10-09T10:00', end: '2026-10-09T11:00' }, items, '2026-10-10')).toEqual([])
    })

    test('a selection shorter than half an hour is its true length here: atom.css draws it at least half an hour tall', () => {
        const [shape] = previewBlocks({ kind: 'select', start: '2026-10-09T10:00', end: '2026-10-09T10:10' }, items, '2026-10-09')

        expect(shape.height).toBeCloseTo(10 / 1440 * 100, 3)
    })
})

test.describe('what a move and a resize draw in the time grid', () => {
    const items = [
        timed('design', '2026-10-09T10:00', '2026-10-09T11:30', { color: 'orange', title: 'Design review' }),
        timed('client', '2026-10-09T10:00', '2026-10-09T11:00', { color: 'red', title: 'Client call' }),
    ]

    test('the item as it would be at the new time, with its own colour and title', () => {
        const preview = { kind: 'move', id: 'design', start: '2026-10-08T13:30', end: '2026-10-08T15:00' }
        const grid = timeGrid(week, items, { today: '2026-10-09', preview })
        const column = grid.columns.find((c) => c.date === '2026-10-08')

        expect(column.preview).toHaveLength(1)
        expect(column.preview[0]).toMatchObject({ kind: 'move', title: 'Design review', color: 'orange', start: '2026-10-08T13:30', end: '2026-10-08T15:00' })
        expect(column.preview[0].top).toBeCloseTo(13.5 / 24 * 100, 3)
        // the other days have none
        expect(grid.columns.filter((c) => c.preview.length).map((c) => c.date)).toEqual(['2026-10-08'])
    })

    test('the original stays where it is, lifted; the others are not', () => {
        const grid = timeGrid(week, items, { today: '2026-10-09', preview: { kind: 'move', id: 'design', start: '2026-10-08T13:30', end: '2026-10-08T15:00' } })
        const blocks = grid.columns.find((c) => c.date === '2026-10-09').blocks

        expect(blocks.find((b) => b.id === 'design')).toMatchObject({ lifted: true, top: expect.closeTo(10 / 24 * 100, 3) })
        expect(blocks.find((b) => b.id === 'client').lifted).toBe(false)
    })

    test('a resize keeps the lane of the item it resizes, and only the end moves', () => {
        const preview = { kind: 'resize', id: 'client', start: '2026-10-09T10:00', end: '2026-10-09T12:15' }
        const column = timeGrid(week, items, { today: '2026-10-09', preview }).columns.find((c) => c.date === '2026-10-09')
        const lane = column.blocks.find((b) => b.id === 'client')

        expect(lane.width).toBe(50)
        expect(column.preview[0]).toMatchObject({ kind: 'resize', left: lane.left, width: lane.width })
        expect(column.preview[0].top).toBeCloseTo(lane.top, 3)
        expect(column.preview[0].height).toBeCloseTo(2.25 / 24 * 100, 3)
    })

    test('an item that is not there is a ghost with no title of its own, and nothing is lifted', () => {
        const grid = timeGrid(week, items, { today: '2026-10-09', preview: { kind: 'move', id: 'gone', start: '2026-10-09T09:00', end: '2026-10-09T10:00' } })

        expect(grid.columns.flatMap((c) => c.blocks).some((b) => b.lifted)).toBe(false)
        expect(grid.columns.find((c) => c.date === '2026-10-09').preview[0].title).toBe('')
    })

    test('a selection lifts nothing', () => {
        const grid = timeGrid(week, items, { today: '2026-10-09', preview: { kind: 'select', id: 'design', start: '2026-10-09T10:00', end: '2026-10-09T11:00' } })

        expect(grid.columns.flatMap((c) => c.blocks).some((b) => b.lifted)).toBe(false)
    })
})

test.describe('what a gesture draws in the month', () => {
    const items = [
        bar('conference', '2026-10-09', '2026-10-14', { color: 'orange', title: 'Conference' }),
        bar('launch', '2026-10-05', '2026-10-09', { color: 'purple' }),
    ]
    const layout = (preview) => monthLayout(days, items, { max: 3, today: '2026-10-09', preview })
    const weekOf = (list, first) => list.find((w) => w.key === first)

    test('a selection is a box over its days, split at the week row, and marks the days', () => {
        const weeks = layout({ kind: 'select', start: '2026-10-11', end: '2026-10-13' })
        const first = weekOf(weeks, '2026-10-05')
        const second = weekOf(weeks, '2026-10-12')

        expect(first.selection).toMatchObject({ kind: 'select', col: 7, span: 1, before: false, after: true })
        expect(first.selection.left).toBeCloseTo(600 / 7, 3)
        expect(first.selection.width).toBeCloseTo(100 / 7, 3)
        expect(second.selection).toMatchObject({ col: 1, span: 2, before: true, after: false })
        expect(first.days.filter((d) => d.selected).map((d) => d.date)).toEqual(['2026-10-11'])
        expect(second.days.filter((d) => d.selected).map((d) => d.date)).toEqual(['2026-10-12', '2026-10-13'])
        expect(weeks.filter((w) => w.selection)).toHaveLength(2)
        // no ghost for a selection
        expect(weeks.some((w) => w.ghost)).toBe(false)
    })

    test('a move is a ghost bar of the item over the days it would cover, and the original is lifted', () => {
        const weeks = layout({ kind: 'move', id: 'conference', start: '2026-10-13', end: '2026-10-17' })
        const week2 = weekOf(weeks, '2026-10-12')

        expect(week2.ghost).toMatchObject({ kind: 'move', title: 'Conference', color: 'orange', col: 2, span: 5, row: 2 })
        expect(week2.selection).toBe(null)
        expect(weeks.flatMap((w) => w.chips).filter((c) => c.lifted).map((c) => c.id)).toEqual(['conference', 'conference'])
        expect(weeks.flatMap((w) => w.chips).filter((c) => c.id === 'launch').every((c) => !c.lifted)).toBe(true)
    })

    test('a resize is the same ghost, ending where the bar would end', () => {
        const weeks = layout({ kind: 'resize', id: 'launch', start: '2026-10-05', end: '2026-10-12' })

        expect(weekOf(weeks, '2026-10-05').ghost).toMatchObject({ kind: 'resize', col: 1, span: 7, after: true })
        expect(weekOf(weeks, '2026-10-12').ghost).toMatchObject({ col: 1, span: 1, before: true })
    })

    test('an item waiting for the host is a striped box over its days and a bar with its title, in front of what is on them', () => {
        const weeks = layout({ kind: 'pending', start: '2026-10-20', end: '2026-10-20', title: 'New task' })

        expect(weekOf(weeks, '2026-10-19').selection).toMatchObject({ kind: 'pending', col: 2, span: 1 })
        expect(weekOf(weeks, '2026-10-19').ghost).toMatchObject({ kind: 'pending', title: 'New task', col: 2, span: 1, row: 2 })
        // nothing is lifted: it is a new item
        expect(weeks.flatMap((w) => w.chips).some((c) => c.lifted)).toBe(false)
    })

    test('no preview, no shapes', () => {
        const weeks = layout(null)

        expect(weeks.every((w) => w.selection === null && w.ghost === null)).toBe(true)
        expect(weeks.flatMap((w) => w.days).some((d) => d.selected)).toBe(false)
    })
})

test.describe('overlapping items one over the next (the cascade)', () => {
    const cascade = (items, date = '2026-10-09') => columnBlocks(items, date, { overlap: 'cascade' })
    const block = (list, id) => list.find((b) => b.id === id)

    test('items that start together share a level side by side; a later start goes one level in, over them', () => {
        const list = cascade([
            timed('a', '2026-10-09T09:00', '2026-10-09T10:30'),
            timed('b', '2026-10-09T09:00', '2026-10-09T10:00'),
            timed('c', '2026-10-09T09:30', '2026-10-09T11:00'),
        ])

        // a and b are one group at level 0: half of the column each
        expect(block(list, 'a')).toMatchObject({ left: 0, width: 50, level: 0 })
        expect(block(list, 'b')).toMatchObject({ left: 50, width: 50, level: 0 })
        // c is one level in, as wide as is left, and in front
        expect(block(list, 'c')).toMatchObject({ left: CASCADE_STEP, width: 100 - CASCADE_STEP, level: 1 })
    })

    test('an item alone is the whole column, and two that do not overlap are not a cascade', () => {
        const list = cascade([timed('a', '2026-10-09T09:00', '2026-10-09T10:00'), timed('b', '2026-10-09T10:00', '2026-10-09T11:00')])

        expect(block(list, 'a')).toMatchObject({ left: 0, width: 100, level: 0 })
        expect(block(list, 'b')).toMatchObject({ left: 0, width: 100, level: 0 })
    })

    test('a chain goes one level in each step, and never further in than CASCADE_MAX of the column in all', () => {
        const items = Array.from({ length: 8 }, (_, i) => timed('t' + i, `2026-10-09T${String(9 + i).padStart(2, '0')}:00`, '2026-10-09T18:00'))
        const list = cascade(items)

        expect(block(list, 't1').level).toBe(1)
        expect(block(list, 't7').level).toBe(7)
        expect(Math.max(...list.map((b) => b.left))).toBeLessThanOrEqual(CASCADE_MAX + 0.001)
        // each is in front of the one before it, and wide enough to read
        expect(list.map((b) => b.level)).toEqual([0, 1, 2, 3, 4, 5, 6, 7])
        expect(Math.min(...list.map((b) => b.width))).toBeGreaterThanOrEqual(100 - CASCADE_MAX - 0.001)
    })

    test('a later item inside a longer one goes in one level, and one that starts after the first ended goes back out', () => {
        const list = cascade([
            timed('long', '2026-10-09T09:00', '2026-10-09T12:00'),
            timed('inside', '2026-10-09T10:00', '2026-10-09T10:30'),
            timed('after', '2026-10-09T12:00', '2026-10-09T13:00'),
        ])

        expect(block(list, 'inside').level).toBe(1)
        expect(block(list, 'after')).toMatchObject({ level: 0, left: 0, width: 100 })
    })

    test('the default is side by side, as before', () => {
        const items = [timed('a', '2026-10-09T09:00', '2026-10-09T10:00'), timed('b', '2026-10-09T09:30', '2026-10-09T10:30')]

        expect(columnBlocks(items, '2026-10-09').map((b) => [b.left, b.width, b.level])).toEqual([[0, 50, 0], [50, 50, 0]])
    })
})

test.describe('the edge of a hex colour', () => {
    test('reads a hex of 3, 4, 6 or 8 digits, and nothing else', () => {
        expect(parseHex('#f80')).toEqual([255, 136, 0])
        expect(parseHex('#f808')).toEqual([255, 136, 0])
        expect(parseHex('#ff8800')).toEqual([255, 136, 0])
        expect(parseHex('#ff880080')).toEqual([255, 136, 0])
        expect(parseHex('#ff88')).toEqual([255, 255, 136])
        expect(parseHex('#ff880')).toBe(null)
        expect(parseHex('#ff88000')).toBe(null)
        expect(parseHex('red')).toBe(null)
        expect(parseHex('#xyz')).toBe(null)
        expect(parseHex(null)).toBe(null)
    })

    test('is moved only as far as it has to be to show against the ground of each theme', () => {
        // a pale orange does not show on white, and is darkened; it already shows on the dark ground, and is left alone
        const orange = edgeColors('#ff8800')

        expect(contrast(parseHex(orange.light), LIGHT_GROUND)).toBeGreaterThanOrEqual(EDGE_CONTRAST)
        expect(orange.light).not.toBe('#ff8800')
        expect(orange.dark).toBe('#ff8800')

        // a navy shows on white, and is lightened for the dark ground
        const navy = edgeColors('#000080')

        expect(navy.light).toBe('#000080')
        expect(contrast(parseHex(navy.dark), DARK_GROUND)).toBeGreaterThanOrEqual(EDGE_CONTRAST)
        expect(navy.dark).not.toBe('#000080')
    })

    test('every colour of the cube shows in both themes, and an extreme becomes black or white rather than staying invisible', () => {
        for (const value of ['#ffffff', '#000000', '#ffee00', '#7fffd4', '#27272a', '#fafafa', '#808080']) {
            const edge = edgeColors(value)

            expect(contrast(parseHex(edge.light), LIGHT_GROUND), value + ' light').toBeGreaterThanOrEqual(EDGE_CONTRAST - 0.001)
            expect(contrast(parseHex(edge.dark), DARK_GROUND), value + ' dark').toBeGreaterThanOrEqual(EDGE_CONTRAST - 0.001)
        }

        expect(edgeColors('nope')).toBe(null)
    })
})
