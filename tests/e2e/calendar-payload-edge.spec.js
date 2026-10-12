import { test, expect } from '@playwright/test'

// Two things about the payload in a browser, on the Livewire fixture (/atom/e2e/calendar, frozen at 2026-10-09 10:30 in Kuala Lumpur, so the October
// window is 2026-09-28 to 2026-11-08): an item whose date will not parse does not blank the calendar, and the layouts the factory keeps (see
// calendar-memo.spec.js for what they are kept on) are kept when they are read the way a binding reads them.

const calendar = (page) => page.locator('[data-probe="calendar"]')
const chip = (page, text) => calendar(page).locator('[data-atom-calendar-chip]:visible', { hasText: text })

async function open (page, query = '') {
    const problems = []

    page.on('pageerror', (error) => problems.push(error.message))
    page.on('console', (message) => {
        if (['error', 'warning'].includes(message.type()) && /alpine|atom:calendar|typeerror/i.test(message.text())) problems.push(message.text())
    })

    await page.goto('/atom/e2e/calendar' + query)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(calendar(page).locator('[data-atom-calendar-body]')).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)

    return problems
}

// hand the calendar a payload of its own: the one it has, with these items added
const addItems = (page, extra) => page.evaluate((items) => {
    const root = document.querySelector('[data-atom-calendar]')
    const payload = JSON.parse(root.getAttribute('data-atom-calendar-payload'))

    payload.items.push(...items.map((item) => ({ color: null, icon: null, danger: false, href: null, draggable: true, resizable: true, end: null, ...item })))
    root.setAttribute('data-atom-calendar-payload', JSON.stringify(payload))

    return payload.items.length
}, extra)

const junk = [
    { id: 'j1', title: 'Junk no start', start: null, allDay: true },
    { id: 'j2', title: 'Junk text start', start: 'next tuesday', allDay: true },
    { id: 'j3', title: 'Junk day 32', start: '2026-10-32', allDay: true },
    { id: 'j4', title: 'Junk timed day 32', start: '2026-10-32T09:00', end: '2026-10-33T10:00', allDay: false },
    { id: 'j5', title: 'Junk year 10000', start: '10000-01-01', allDay: true },
    { id: 'j6', title: 'Junk year 0', start: '0000-10-12', allDay: true },
    { id: 'j7', title: 'Junk number', start: 1791500000, allDay: true },
]

const good = [
    { id: 'g1', title: 'Good all-day', start: '2026-10-13', end: '2026-10-14', allDay: true },
    { id: 'g2', title: 'Good timed', start: '2026-10-13T09:00', end: '2026-10-13T10:00', allDay: false },
    // an end that is not a date is no end
    { id: 'g3', title: 'Good bad end', start: '2026-10-15', end: '2026-10-99', allDay: true },
    { id: 'g4', title: 'Good timed bad end', start: '2026-10-15T11:00', end: 'later', allDay: false },
]

for (const view of ['month', 'week', 'day']) {
    test(`${view}: a payload with items whose date will not parse shows the others and throws nothing`, async ({ page }) => {
        const problems = await open(page, '?view=month&date=2026-10-13')
        const total = await addItems(page, [...junk, ...good])

        await page.evaluate((next) => window.Alpine.$data(document.querySelector('[data-atom-calendar]')).setView(next), view)

        await expect.poll(() => page.evaluate(() => window.Alpine.$data(document.querySelector('[data-atom-calendar]')).payload.items.length)).toBe(total)

        // what the view shows of the 13th to the 15th: the day view is the 13th alone
        const shown = view === 'day' ? ['Good all-day', 'Good timed'] : ['Good all-day', 'Good timed', 'Good bad end', 'Good timed bad end']

        for (const title of shown) await expect(chip(page, title).first()).toBeVisible()

        for (const item of junk) await expect(chip(page, item.title)).toHaveCount(0)

        // the rest of the calendar is as it was: the fixture's own items are still there
        expect(await calendar(page).locator('[data-atom-calendar-chip]').count()).toBeGreaterThan(good.length)

        // a day with nothing but junk on it lists nothing
        expect(problems).toEqual([])
    })
}

test('a gesture over a calendar that holds such items still works', async ({ page }) => {
    const problems = await open(page, '?view=month&date=2026-10-13&selectable=1')

    await addItems(page, [...junk, ...good])
    await expect(chip(page, 'Good all-day').first()).toBeVisible()

    // select two days with the mouse
    const cell = (date) => calendar(page).locator(`[data-atom-calendar-day][data-date="${date}"]`)
    const from = await cell('2026-10-20').boundingBox()
    const to = await cell('2026-10-21').boundingBox()

    await page.mouse.move(from.x + 10, from.y + from.height - 8)
    await page.mouse.down()
    await page.mouse.move(to.x + 10, to.y + to.height - 8, { steps: 6 })

    await expect.poll(() => page.evaluate(() => window.Alpine.$data(document.querySelector('[data-atom-calendar]')).preview?.kind)).toBe('select')

    await page.mouse.up()
    expect(problems).toEqual([])
})

test('a binding reads the layouts the way the factory kept them: one answer, however many times it is asked', async ({ page }) => {
    await open(page, '?view=week&date=2026-10-14')

    const answers = await page.evaluate(() => {
        const root = document.querySelector('[data-atom-calendar]')
        const asked = (expression) => window.Alpine.evaluate(root, expression)

        return {
            weeks: asked('weeks') === asked('weeks'),
            grid: asked('grid') === asked('grid'),
            items: asked('items') === asked('items'),
            gridDays: asked('gridDays') === asked('gridDays'),
            // and through the data proxy, as a test reads them
            data: window.Alpine.$data(root).weeks === window.Alpine.evaluate(root, 'weeks'),
        }
    })

    expect(answers).toEqual({ weeks: true, grid: true, items: true, gridDays: true, data: true })
})

test('a cell the pointer enters lays the month out once, however many bindings read it', async ({ page }) => {
    await open(page, '?view=month&date=2026-10-13')

    const result = await page.evaluate(async () => {
        const root = document.querySelector('[data-atom-calendar]')
        const data = window.Alpine.$data(root)
        const days = data.payload.window.days.map((day) => day.date)
        const settle = () => new Promise((resolve) => window.Alpine.nextTick(resolve))
        const out = []

        for (let i = 0; i < 6; i++) {
            const before = window.Alpine.evaluate(root, 'weeks')

            data.preview = { kind: 'move', id: 'g', start: days[14 + i], end: null, allDay: true }
            await settle()

            // asked the way twenty bindings would
            const answers = Array.from({ length: 20 }, () => window.Alpine.evaluate(root, 'weeks'))

            out.push({ same: answers.every((answer) => answer === answers[0]), changed: answers[0] !== before })
        }

        return out
    })

    expect(result).toEqual(Array.from({ length: 6 }, () => ({ same: true, changed: true })))
})
