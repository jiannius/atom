import { test, expect } from '@playwright/test'
import calendar from '../../resources/js/alpinejs/calendar.js'
import { addDays } from '../../resources/js/alpinejs/calendar/dates.js'

// The calendar factory's own logic, driven in node with no Alpine and no DOM: a payload is put on it
// by hand, and the getters and methods are asked what they answer. The browser behaviour is in
// calendar.spec.js.

const palette = {
    blue: { tint: 'tint-blue', accent: 'accent-blue', dot: 'dot-blue' },
    zinc: { tint: 'tint-zinc', accent: 'accent-zinc', dot: 'dot-zinc' },
    red: { tint: 'tint-red', accent: 'accent-red', dot: 'dot-red' },
}

test.describe('colour classes', () => {
    test('an item with no colour is zinc, whatever a host did to blue', () => {
        const engine = calendar()

        // a host that restyles blue (its brand colour, say) must not recolour the items that never asked for it
        engine.palette = { ...palette, blue: { tint: 'tint-brand', accent: 'accent-brand', dot: 'dot-brand' } }

        expect(engine.colorClass({ color: null }, 'tint')).toBe('tint-zinc')
        expect(engine.colorClass({ color: null }, 'accent')).toBe('accent-zinc')
        expect(engine.colorClass({ color: null }, 'dot')).toBe('dot-zinc')
    })

    test('a colour the palette does not know falls back to zinc as well', () => {
        const engine = calendar()

        engine.palette = palette

        expect(engine.colorClass({ color: 'chartreuse' }, 'dot')).toBe('dot-zinc')
    })

    test('a named colour keeps its own classes, and a hex has none', () => {
        const engine = calendar()

        engine.palette = palette

        expect(engine.colorClass({ color: 'red' }, 'tint')).toBe('tint-red')
        expect(engine.colorClass({ color: '#ff8800' }, 'tint')).toBe('')
    })
})

// A payload as the server builds it, as far as the factory reads it: the window's days, the labels the formatter
// needs, the config, and no items.
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const payloadFor = (days, extra = {}) => ({
    anchor: days[0],
    today: days[0],
    window: { start: days[0], end: days[days.length - 1], days: days.map((date) => ({ date, weekend: false, outside: false })) },
    labels: {
        weekdays: [],
        months: monthNames,
        months_short: monthNames.map((name) => name.slice(0, 3)),
        meridiem: [[0, 'am', 'AM'], [720, 'pm', 'PM']],
    },
    config: { views: ['month', 'week', 'day'], timeFormat: 'g:i A', dateFormat: 'j M Y', slotDuration: 30, nowIndicator: true },
    items: [],
    ...extra,
})

// 42 days from a Monday
const windowFrom = (start) => Array.from({ length: 42 }, (_, i) => addDays(start, i))

const engineAt = (days, view, anchor) => {
    const engine = calendar()

    engine.payload = payloadFor(days, { anchor })
    engine.view = view
    engine.anchor = anchor
    engine.$refs = {}

    return engine
}

test.describe('what the toolbar names', () => {
    test('a month, a week in one month, a week over two, a week over a new year, and a day', () => {
        const days = windowFrom('2026-12-28')
        const titleOf = (view, anchor) => engineAt(days, view, anchor).title

        expect(titleOf('month', '2027-01-05')).toMatchObject({ month: 'January', year: '2027' })
        expect(titleOf('week', '2026-12-30')).toEqual({ month: '28 Dec 2026 – 3 Jan', year: '2027', text: '28 Dec 2026 – 3 Jan 2027' })
        expect(titleOf('week', '2027-01-06')).toMatchObject({ month: '4 – 10 January', year: '2027' })
        expect(titleOf('day', '2027-01-06')).toMatchObject({ month: '6 January', year: '2027' })

        const over = engineAt(windowFrom('2026-09-28'), 'week', '2026-10-30').title

        expect(over).toMatchObject({ month: '26 Oct – 1 Nov', year: '2026' })
    })

    test('the days a view shows: the week of the anchor, or the anchor', () => {
        const days = windowFrom('2026-09-28')

        expect(engineAt(days, 'week', '2026-10-09').gridDays.map((day) => day.date)).toEqual(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11'])
        expect(engineAt(days, 'day', '2026-10-09').gridDays.map((day) => day.date)).toEqual(['2026-10-09'])
        // the first and the last day of the window are in the first and the last week
        expect(engineAt(days, 'week', '2026-09-28').gridDays[0].date).toBe('2026-09-28')
        expect(engineAt(days, 'week', '2026-11-08').gridDays[6].date).toBe('2026-11-08')
    })
})

test.describe('stepping', () => {
    const steps = (view, anchor, direction, pending = null) => {
        const engine = engineAt(windowFrom('2026-09-28'), view, anchor)
        const targets = []

        engine.navigate = (date) => targets.push(date)
        engine.pending = pending
        engine.step(direction)

        return targets[0]
    }

    test('a month steps by month, a week by seven days, a day by one', () => {
        expect(steps('month', '2026-10-09', 1)).toBe('2026-11-09')
        expect(steps('week', '2026-10-09', 1)).toBe('2026-10-16')
        expect(steps('week', '2026-10-09', -1)).toBe('2026-10-02')
        expect(steps('day', '2026-10-09', 1)).toBe('2026-10-10')
        expect(steps('day', '2026-10-01', -1)).toBe('2026-09-30')
    })

    test('with a window on its way a step starts from where that is heading', () => {
        expect(steps('week', '2026-10-09', 1, '2026-11-13')).toBe('2026-11-20')
        expect(steps('day', '2026-10-09', 1, '2026-11-13')).toBe('2026-11-14')
        expect(steps('month', '2026-10-09', 1, '2026-12-09')).toBe('2027-01-09')
    })
})

test.describe('changing view', () => {
    test('keeps the anchor, and only a return to another month asks the server', () => {
        const engine = engineAt(windowFrom('2026-09-28'), 'month', '2026-10-09')
        const asked = []

        engine.navigate = (date) => asked.push(date)

        engine.setView('week')
        engine.setView('day')
        engine.setView('week')
        expect(engine.anchor).toBe('2026-10-09')

        // back in the month on screen: nothing to ask
        engine.setView('month')
        expect(asked).toEqual([])
        expect(engine.view).toBe('month')

        // a week moved on into November, then back to the month view: the server is asked for November
        engine.setView('week')
        engine.anchor = '2026-11-04'
        engine.setView('month')
        expect(asked).toEqual(['2026-11-04'])
    })

    test('ignores a view that is not offered', () => {
        const engine = engineAt(windowFrom('2026-09-28'), 'month', '2026-10-09')

        engine.payload.config.views = ['month']
        engine.setView('week')

        expect(engine.view).toBe('month')
    })
})

test.describe('the now line', () => {
    test('is in the column of the date the clock is on, at its minute, and nowhere with no clock or when switched off', () => {
        const engine = engineAt(windowFrom('2026-09-28'), 'week', '2026-10-09')

        engine.nowMoment = '2026-10-09T10:30'

        expect(engine.nowLine({ date: '2026-10-09' })).toEqual({ top: (630 / 1440 * 100) + '%', time: '10:30 AM' })
        expect(engine.nowLine({ date: '2026-10-10' })).toBeNull()

        engine.nowMoment = ''
        expect(engine.nowLine({ date: '2026-10-09' })).toBeNull()

        engine.nowMoment = '2026-10-09T00:00'
        expect(engine.nowLine({ date: '2026-10-09' })).toEqual({ top: '0%', time: '12:00 AM' })

        engine.payload.config.nowIndicator = false
        expect(engine.nowLine({ date: '2026-10-09' })).toBeNull()
    })
})

test.describe('blocks', () => {
    test('sit by percentages of the day and of the column, never shorter than 20px, and moved up to stay inside the day', () => {
        const engine = calendar()

        expect(engine.blockStyle({ top: 37.5, height: 4.1667, left: 50, width: 50 })).toEqual({
            top: 'min(37.5%, calc(100% - 20px))',
            height: '4.1667%',
            left: '50%',
            width: '50%',
        })
    })

    test('hours are labelled without their minutes in a 12-hour format, and as they are in a 24-hour one', () => {
        const engine = engineAt(windowFrom('2026-09-28'), 'week', '2026-10-09')

        expect([0, 1, 12, 13, 23].map((hour) => engine.hourLabel(hour))).toEqual(['', '1 AM', '12 PM', '1 PM', '11 PM'])

        engine.payload.config.timeFormat = 'H:i'
        expect([0, 9, 18].map((hour) => engine.hourLabel(hour))).toEqual(['', '09:00', '18:00'])
    })

    test('print their times from the formatter: a range, or the start alone', () => {
        const engine = engineAt(windowFrom('2026-09-28'), 'week', '2026-10-09')

        expect(engine.timeRange({ start: '2026-10-09T09:30', end: '2026-10-09T10:00' })).toBe('9:30 AM – 10:00 AM')
        expect(engine.timeRange({ start: '2026-10-09T15:00', end: null })).toBe('3:00 PM')
    })
})
