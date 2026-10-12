import { test, expect } from '@playwright/test'
import calendar from '../../resources/js/alpinejs/calendar.js'
import { addDays } from '../../resources/js/alpinejs/calendar/dates.js'

// The factory's layouts (weeks, grid, previews, allDayItemGhost) are kept while what they are worked out from is the same, so that a drag that moves
// one cell lays the month out once and not once per binding. The risk of that is an answer that is kept when it should not be, which is what is
// pinned here: each input is changed in turn, and the answer must be what a calendar that was never asked before gives. Driven in node, as in
// calendar-factory.spec.js; what a binding reads in a browser is in calendar-payload-edge.spec.js.

const days = Array.from({ length: 42 }, (_, i) => addDays('2026-09-28', i))
const item = (id, start, end = null, extra = {}) => ({ id, title: String(id), start, end, allDay: !String(start).includes('T'), color: null, icon: null, danger: false, href: null, draggable: true, resizable: true, ...extra })

const items = () => [
    item('a', '2026-10-05', '2026-10-07'),
    item('b', '2026-10-06T09:00', '2026-10-06T10:00'),
    item('c', '2026-10-06T09:30', '2026-10-06T11:00'),
    item('d', '2026-10-09'),
    item('e', '2026-10-08T14:00', '2026-10-08T15:00'),
]

const payloadFor = (list = items(), config = {}) => ({
    anchor: '2026-10-09',
    today: '2026-10-09',
    window: { start: days[0], end: days[41], days: days.map((date) => ({ date, weekend: false, outside: false })) },
    labels: { weekdays: [], months: [], months_short: [], meridiem: [], week_title: {} },
    config: { views: ['month', 'week', 'day'], timeFormat: 'g:i A', slotDuration: 30, max: 3, ...config },
    items: list,
})

const engineWith = (state = {}) => {
    const engine = calendar()

    engine.payload = state.payload ?? payloadFor()
    engine.view = state.view ?? 'month'
    engine.anchor = state.anchor ?? '2026-10-09'
    engine.todayDate = state.todayDate ?? '2026-10-09'
    engine.preview = state.preview ?? null
    engine.overrides = state.overrides ?? {}

    return engine
}

// what a calendar in the same state that has never been asked gives, as text
const fresh = (engine) => {
    const twin = engineWith({
        payload: JSON.parse(JSON.stringify(engine.payload)),
        view: engine.view,
        anchor: engine.anchor,
        todayDate: engine.todayDate,
        preview: engine.preview ? JSON.parse(JSON.stringify(engine.preview)) : null,
        overrides: JSON.parse(JSON.stringify(engine.overrides)),
    })

    return { weeks: JSON.stringify(twin.weeks), grid: JSON.stringify(twin.grid), previews: JSON.stringify(twin.previews), ghost: JSON.stringify(twin.allDayItemGhost), items: JSON.stringify(twin.items), gridDays: JSON.stringify(twin.gridDays) }
}

const asked = (engine) => ({ weeks: JSON.stringify(engine.weeks), grid: JSON.stringify(engine.grid), previews: JSON.stringify(engine.previews), ghost: JSON.stringify(engine.allDayItemGhost), items: JSON.stringify(engine.items), gridDays: JSON.stringify(engine.gridDays) })

// ask, change something, ask again: the second answer is what a new calendar says, and (when `moves`) it is not the first one
const stays = (engine, change, moves = engine.view === 'month' ? ['weeks'] : ['grid']) => {
    const before = asked(engine)

    expect(asked(engine)).toEqual(before)
    expect(fresh(engine)).toEqual(before)

    change(engine)

    const after = asked(engine)

    expect(after).toEqual(fresh(engine))

    for (const name of moves) expect(after[name], name).not.toBe(before[name])
}

test.describe('a layout is kept while its inputs are the same', () => {
    test('the same answer, not a new one, until something changes', () => {
        const engine = engineWith()

        expect(engine.weeks).toBe(engine.weeks)
        expect(engine.items).toBe(engine.items)
        expect(engine.gridDays).toBe(engine.gridDays)

        engine.view = 'week'

        expect(engine.grid).toBe(engine.grid)
        expect(engine.previews).toBe(engine.previews)

        engine.preview = { kind: 'move', id: 'b', start: '2026-10-07T09:00', end: '2026-10-07T10:00', allDay: false }

        expect(engine.previews).toBe(engine.previews)
        expect(engine.allDayItemGhost).toBe(engine.allDayItemGhost)
    })

    test('a preview that changes leaves the time grid as it was, and lays the previews out again', () => {
        const engine = engineWith({ view: 'week' })
        const grid = engine.grid

        engine.preview = { kind: 'move', id: 'b', start: '2026-10-07T09:00', end: '2026-10-07T10:00', allDay: false }

        const previews = engine.previews

        engine.preview = { kind: 'move', id: 'b', start: '2026-10-08T09:00', end: '2026-10-08T10:00', allDay: false }

        expect(engine.grid).toBe(grid)
        expect(engine.previews).not.toBe(previews)
        expect(engine.previews['2026-10-08']).toHaveLength(1)
        expect(engine.previews['2026-10-07']).toHaveLength(0)
    })

    test('a calendar does not borrow the answer of another', () => {
        const one = engineWith()
        const other = engineWith({ payload: payloadFor([item('z', '2026-10-12')]) })

        expect(JSON.stringify(one.weeks)).not.toBe(JSON.stringify(other.weeks))
        expect(one.weeks).not.toBe(other.weeks)
    })
})

test.describe('and laid out again as soon as one of them changes', () => {
    test('the payload', () => {
        stays(engineWith(), (engine) => { engine.payload = payloadFor([item('a', '2026-10-05', '2026-10-07'), item('new', '2026-10-13')]) })
        stays(engineWith({ view: 'week' }), (engine) => { engine.payload = payloadFor([item('b', '2026-10-06T09:00', '2026-10-06T10:00'), item('new', '2026-10-07T13:00', '2026-10-07T14:00')]) })
    })

    test('the payload\'s items alone, and its window', () => {
        stays(engineWith(), (engine) => { engine.payload = { ...engine.payload, items: [item('only', '2026-10-20')] } })
        stays(engineWith(), (engine) => { engine.payload = { ...engine.payload, window: { ...engine.payload.window, days: engine.payload.window.days.slice(7) } } })
    })

    test('one part of the payload when the rest of it is the same objects', () => {
        // the items and the window are separate objects of the payload: a payload that replaces one keeps the other, and what is worked out from the
        // kept one must not be given again for the new one
        const moved = { d: { range: { start: '2026-10-21', end: null }, token: 1, held: false, stale: false } }

        stays(engineWith({ overrides: moved }), (engine) => { engine.payload = { ...engine.payload, items: [item('d', '2026-10-09'), item('fresh', '2026-10-14')] } })

        // the week is the seven days of the window the anchor is in, so a window that starts on another day, under the same anchor, has another week
        const shifted = engineWith({ view: 'week', anchor: '2026-10-20' })

        stays(shifted, (engine) => { engine.payload = { ...engine.payload, window: { ...engine.payload.window, days: engine.payload.window.days.slice(3) } } })
        expect(shifted.gridDays.map((day) => day.date)).toEqual(Array.from({ length: 7 }, (_, i) => addDays('2026-10-15', i)))

        // the ghost of an all-day item is placed over the days of the week, so it moves with them
        const away = { kind: 'move', id: 'd', start: '2026-10-20', end: null, allDay: true }

        stays(engineWith({ view: 'week', anchor: '2026-10-20', preview: away }), (engine) => { engine.payload = { ...engine.payload, window: { ...engine.payload.window, days: engine.payload.window.days.slice(3) } } }, ['ghost'])

        // an all-day item that is being moved is drawn as it is in the payload: when the payload says something else of it, so does its ghost
        const ghost = { kind: 'move', id: 'd', start: '2026-10-08', end: null, allDay: true }

        stays(engineWith({ view: 'week', preview: ghost }), (engine) => { engine.payload = { ...engine.payload, items: engine.payload.items.map((it) => (it.id === 'd' ? { ...it, title: 'Renamed', color: 'red' } : it)) } }, ['ghost'])
        expect(engineWith({ view: 'week', preview: ghost }).allDayItemGhost.title).toBe('d')
    })

    test('the rows a day shows in the all-day row of the time grid', () => {
        const crowd = [1, 2, 3, 4, 5].map((n) => item('r' + n, '2026-10-06'))
        const week = engineWith({ view: 'week', payload: payloadFor(crowd, { max: 3 }) })

        expect(week.grid.allDay.more[0].count).toBe(2)

        week.payload.config = { ...week.payload.config, max: 1 }

        expect(week.grid.allDay.more[0].count).toBe(4)
        expect(asked(week)).toEqual(fresh(week))
    })

    test('the items that were moved or resized', () => {
        stays(engineWith(), (engine) => { engine.overrides = { d: { range: { start: '2026-10-21', end: null }, token: 1, held: false, stale: false } } })
        stays(engineWith({ view: 'week' }), (engine) => { engine.overrides = { e: { range: { start: '2026-10-08T18:00', end: '2026-10-08T19:30' }, token: 1, held: false, stale: false } } })

        // and where they were put, then put somewhere else, then let go
        const engine = engineWith({ overrides: { d: { range: { start: '2026-10-21', end: null }, token: 1, held: false, stale: false } } })

        stays(engine, (it) => { it.overrides = { d: { range: { start: '2026-10-22', end: null }, token: 2, held: false, stale: false } } })
        stays(engine, (it) => { it.overrides = {} })
    })

    test('the preview, replaced and changed in place', () => {
        stays(engineWith(), (engine) => { engine.preview = { kind: 'select', start: '2026-10-13', end: '2026-10-15', allDay: true } })
        stays(engineWith({ preview: { kind: 'select', start: '2026-10-13', end: '2026-10-15', allDay: true } }), (engine) => { engine.preview.end = '2026-10-17' }, ['weeks'])
        stays(engineWith({ preview: { kind: 'move', id: 'd', start: '2026-10-13', end: null, allDay: true } }), (engine) => { engine.preview = null }, ['weeks'])
        stays(engineWith({ view: 'week', preview: { kind: 'move', id: 'b', start: '2026-10-07T09:00', end: '2026-10-07T10:00', allDay: false } }), (engine) => { engine.preview.start = '2026-10-07T11:00' }, ['previews'])
        stays(engineWith({ view: 'week', preview: { kind: 'move', id: 'd', start: '2026-10-07', end: null, allDay: true } }), (engine) => { engine.preview = { kind: 'move', id: 'd', start: '2026-10-08', end: null, allDay: true } }, ['ghost'])
    })

    test('today, so that the marker moves with the date', () => {
        stays(engineWith(), (engine) => { engine.todayDate = '2026-10-10' })
        stays(engineWith({ view: 'week' }), (engine) => { engine.todayDate = '2026-10-10' })

        const engine = engineWith({ view: 'week' })

        expect(engine.grid.columns.find((column) => column.today).date).toBe('2026-10-09')

        engine.todayDate = '2026-10-10'

        expect(engine.grid.columns.find((column) => column.today).date).toBe('2026-10-10')
        expect(engine.weeks.flatMap((week) => week.days).find((day) => day.today).date).toBe('2026-10-10')
    })

    test('the view, and the anchor the week or day is of', () => {
        const engine = engineWith({ view: 'month' })

        expect(engine.grid.columns).toEqual([])

        engine.view = 'week'

        expect(engine.grid.columns).toHaveLength(7)

        engine.view = 'day'

        expect(engine.grid.columns).toHaveLength(1)
        expect(engine.gridDays.map((day) => day.date)).toEqual(['2026-10-09'])

        engine.anchor = '2026-10-13'

        expect(engine.gridDays.map((day) => day.date)).toEqual(['2026-10-13'])
        expect(engine.grid.columns[0].date).toBe('2026-10-13')

        engine.view = 'week'

        expect(engine.grid.columns.map((column) => column.date)).toEqual(Array.from({ length: 7 }, (_, i) => addDays('2026-10-12', i)))

        engine.anchor = '2026-10-04'

        expect(engine.grid.columns[0].date).toBe('2026-09-28')

        engine.view = 'month'

        expect(engine.weeks).toHaveLength(6)
        expect(asked(engine)).toEqual(fresh(engine))
    })

    test('the config the layouts read: how many rows a day shows, and how long a slot is', () => {
        const crowd = [1, 2, 3, 4, 5].map((n) => item('c' + n, '2026-10-05'))

        stays(engineWith({ payload: payloadFor(crowd, { max: 3 }) }), (engine) => { engine.payload = payloadFor(crowd, { max: 2 }) })

        const engine = engineWith({ payload: payloadFor(crowd, { max: 3 }) })

        expect(engine.weeks[1].more[0].count).toBe(2)

        engine.payload.config = { ...engine.payload.config, max: 1 }

        expect(engine.weeks[1].more[0].count).toBe(4)

        const week = engineWith({ view: 'week', payload: payloadFor([item('s', '2026-10-06T09:00')], { slotDuration: 30 }) })

        expect(week.grid.columns[1].blocks[0].endMinute - week.grid.columns[1].blocks[0].startMinute).toBe(30)

        week.payload.config = { ...week.payload.config, slotDuration: 60 }

        expect(week.grid.columns[1].blocks[0].endMinute - week.grid.columns[1].blocks[0].startMinute).toBe(60)
    })
})
