import { test, expect } from '@playwright/test'

// Selecting, to create, in a browser: a click or a drag on empty space in the month, the all-day row and the time grid, and what the host is
// told (`calendar-select`, in the viewer's wall clock) and when. Real mouse events at points measured before anything moves.
//
// Two pages: the Livewire fixture (/atom/e2e/calendar; ?selectable=1, frozen at 2026-10-09 10:30 in Kuala Lumpur, so October's window is
// 2026-09-28 to 2026-11-08) with a host script that records every select (window.__selects) and answers by window.__selectMode, and the
// static grid page (/atom/e2e/calendar-grid, ?selectable=1) for a viewer's own week start, timezone and slot length. The rig has no
// Tailwind: what is measured here is what atom.css owns, the hour height (3.5rem) and the week's columns.

const HOUR = 3.5

const probe = (page, name = 'calendar') => page.locator(`[data-probe="${name}"]`)
const cell = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-day][data-date="${date}"]`)
const column = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-column][data-date="${date}"]`)
const allDayCell = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-allday-bg] > [data-date="${date}"]`)
const popover = (page, name) => probe(page, name).locator('[data-atom-calendar-popover]')
const boxes = (page, name) => probe(page, name).locator('[data-atom-calendar-week] [data-atom-calendar-selection]')
const ghosts = (page, name) => probe(page, name).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')
const shapes = (page, name) => probe(page, name).locator('[data-atom-calendar-column] [data-atom-calendar-preview]')
const allDayBoxes = (page, name) => probe(page, name).locator('[data-atom-calendar-allday-over] [data-atom-calendar-selection]')

async function open (page, query = '', path = '/atom/e2e/calendar') {
    await page.goto(path + (query ? '?' + query : ''))
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)

    await page.evaluate(() => {
        window.__clicks = []
        document.addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail.id))
    })
}

const selects = (page) => page.evaluate(() => window.__selects)
const engine = (page) => page.evaluate(() => {
    const { preview, gesture } = window.Alpine.$data(document.querySelector('[data-atom-calendar]'))

    return { preview: preview ? { ...preview } : null, gesture: gesture !== null }
})

// a point in a month day, low in the cell: clear of the day number and the chips
async function inDay (page, date, name, where = 0.9) {
    const box = await cell(page, date, name).boundingBox()

    return { x: box.x + box.width / 2, y: box.y + box.height * where }
}

// a point in a day's column at a time of day, as the hours of the grid say it (3.5rem each)
async function at (page, date, hour, name) {
    const box = await column(page, date, name).boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

    return { x: box.x + box.width / 2, y: box.y + hour * HOUR * rem }
}

async function inAllDay (page, date, name) {
    const box = await allDayCell(page, date, name).boundingBox()

    return { x: box.x + box.width / 2, y: box.y + box.height - 4 }
}

async function drag (page, from, ...through) {
    await page.mouse.move(from.x, from.y)
    await page.mouse.down()

    for (const point of through) await page.mouse.move(point.x, point.y, { steps: 6 })

    await page.mouse.up()
}

async function click (page, point) {
    await page.mouse.move(point.x, point.y)
    await page.mouse.down()
    await page.mouse.up()
}

// a pointer of the given kind, on the page, as the browser sends one
const pointer = (page, type, point, pointerType = 'touch', id = 7) => page.evaluate(([kind, x, y, pointerKind, pointerId]) => {
    const target = document.elementFromPoint(x, y) ?? document.body

    target.dispatchEvent(new PointerEvent(kind, { pointerId, pointerType: pointerKind, isPrimary: true, button: 0, bubbles: true, cancelable: true, composed: true, clientX: x, clientY: y }))
}, [type, point.x, point.y, pointerType, id])

// whether the page may scroll under a finger that moves now: false when the calendar has prevented the touchmove
const touchMoveAllowed = (page, point) => page.evaluate(([x, y]) => {
    const target = document.elementFromPoint(x, y)
    const event = new Event('touchmove', { bubbles: true, cancelable: true })

    target.dispatchEvent(event)

    return !event.defaultPrevented
}, [point.x, point.y])

test.describe('a month day', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'selectable=1')
    })

    test('a click on an empty day selects that day, and tells the host once, in the format a date has', async ({ page }) => {
        await click(page, await inDay(page, '2026-10-12'))

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-12', allDay: true }])
        await expect(boxes(page)).toHaveCount(1)

        // one day wide
        const box = await boxes(page).boundingBox()
        const day = await cell(page, '2026-10-12').boundingBox()

        expect(box.width).toBeGreaterThan(day.width - 2)
        expect(box.width).toBeLessThan(day.width + 2)
        expect(Math.abs(box.x - day.x)).toBeLessThan(2)
    })

    test('a drag across days of a week selects them, the last included, and draws the box while the pointer is down', async ({ page }) => {
        const from = await inDay(page, '2026-10-12')
        const to = await inDay(page, '2026-10-15')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })

        // before it is let go: drawn, and nothing told
        await expect(boxes(page)).toHaveCount(1)
        expect(await selects(page)).toEqual([])

        const first = await cell(page, '2026-10-12').boundingBox()
        const box = await boxes(page).boundingBox()

        expect(Math.abs(box.width - first.width * 4)).toBeLessThan(3)

        await page.mouse.up()

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-15', allDay: true }])
    })

    test('a drag backwards is the same range', async ({ page }) => {
        await drag(page, await inDay(page, '2026-10-15'), await inDay(page, '2026-10-12'))

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-15', allDay: true }])
    })

    test('a drag across weeks is one range with a box in each week it covers', async ({ page }) => {
        await drag(page, await inDay(page, '2026-10-15'), await inDay(page, '2026-10-21'))

        expect(await selects(page)).toEqual([{ start: '2026-10-15', end: '2026-10-21', allDay: true }])
        // the weeks of the 12th and of the 19th
        await expect(boxes(page)).toHaveCount(2)
    })

    test('a drag across the end of the month, onto the days of the next one that the grid shows, is a range there too', async ({ page }) => {
        await drag(page, await inDay(page, '2026-10-30'), await inDay(page, '2026-11-03'))

        expect(await selects(page)).toEqual([{ start: '2026-10-30', end: '2026-11-03', allDay: true }])
    })

    test('a drag that leaves the grid stays on the last day it was on', async ({ page }) => {
        const from = await inDay(page, '2026-10-15')
        const week = await cell(page, '2026-10-18').boundingBox()

        await drag(page, from, { x: week.x + week.width + 300, y: week.y + week.height * 0.9 })

        expect(await selects(page)).toEqual([{ start: '2026-10-15', end: '2026-10-18', allDay: true }])
    })

    test('a day with items can be selected on its empty space, and opens no list', async ({ page }) => {
        await click(page, await inDay(page, '2026-10-21'))

        expect(await selects(page)).toEqual([{ start: '2026-10-21', end: '2026-10-21', allDay: true }])
        await expect(popover(page)).toBeHidden()
    })

    test('an item takes its own click and selects nothing; so does "+N more", which opens the list', async ({ page }) => {
        const standup = probe(page).locator('[data-atom-calendar-chip="dot"]', { hasText: 'Team standup' })

        await standup.click()
        expect(await page.evaluate(() => window.__clicks)).toEqual([1])

        await probe(page).locator('[data-atom-calendar-more]').first().click()

        await expect(popover(page)).toBeVisible()
        expect(await selects(page)).toEqual([])
        await expect(boxes(page)).toHaveCount(0)
    })

    test('a drag that starts on an item selects nothing', async ({ page }) => {
        const standup = await probe(page).locator('[data-atom-calendar-chip="dot"]', { hasText: 'Team standup' }).boundingBox()

        await drag(page, { x: standup.x + 20, y: standup.y + standup.height / 2 }, await inDay(page, '2026-10-12'))

        expect(await selects(page)).toEqual([])
        await expect(boxes(page)).toHaveCount(0)
    })

    test('the right button, and a drag of the text, select nothing', async ({ page }) => {
        const point = await inDay(page, '2026-10-12')

        await page.mouse.move(point.x, point.y)
        await page.mouse.down({ button: 'right' })
        await page.mouse.up({ button: 'right' })

        expect(await selects(page)).toEqual([])
    })

    test('the pointer does not select the text of the days while it drags', async ({ page }) => {
        await drag(page, await inDay(page, '2026-10-12'), await inDay(page, '2026-10-20'))

        expect(await page.evaluate(() => window.getSelection().toString())).toBe('')
    })
})

test.describe('a calendar that is not selectable', () => {
    test('a click and a drag on empty days select nothing, draw nothing and tell the host nothing', async ({ page }) => {
        await open(page)

        await click(page, await inDay(page, '2026-10-12'))
        await drag(page, await inDay(page, '2026-10-13'), await inDay(page, '2026-10-16'))

        expect(await selects(page)).toEqual([])
        await expect(boxes(page)).toHaveCount(0)
    })

    test('nor does the time grid, nor its all-day row', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12')

        await click(page, await at(page, '2026-10-12', 10))
        await drag(page, await at(page, '2026-10-13', 9), await at(page, '2026-10-13', 11))
        await drag(page, await inAllDay(page, '2026-10-13'), await inAllDay(page, '2026-10-15'))

        expect(await selects(page)).toEqual([])
        await expect(shapes(page)).toHaveCount(0)
        await expect(allDayBoxes(page)).toHaveCount(0)
    })

    test('a day with items opens its list on a click, as before', async ({ page }) => {
        await open(page)

        await cell(page, '2026-10-21').click()
        await expect(popover(page)).toBeVisible()
        expect(await selects(page)).toEqual([])
    })
})

test.describe('Escape', () => {
    test('gives up a selection that is being made: nothing is drawn, nothing told, even when the pointer is let go after', async ({ page }) => {
        await open(page, 'selectable=1')

        const from = await inDay(page, '2026-10-12')
        const to = await inDay(page, '2026-10-15')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(boxes(page)).toHaveCount(1)

        await page.keyboard.press('Escape')
        await expect(boxes(page)).toHaveCount(0)
        expect((await engine(page)).gesture).toBe(false)

        // the pointer is still down: moving it, and letting go, does nothing
        await page.mouse.move(from.x, from.y, { steps: 4 })
        await page.mouse.up()

        expect(await selects(page)).toEqual([])
        await expect(boxes(page)).toHaveCount(0)
    })

    test('gives up a selection in the time grid', async ({ page }) => {
        await open(page, 'selectable=1&view=week&date=2026-10-12')

        await page.mouse.move((await at(page, '2026-10-13', 9)).x, (await at(page, '2026-10-13', 9)).y)
        await page.mouse.down()
        await page.mouse.move((await at(page, '2026-10-13', 11)).x, (await at(page, '2026-10-13', 11)).y, { steps: 6 })
        await expect(shapes(page)).toHaveCount(1)

        await page.keyboard.press('Escape')
        await expect(shapes(page)).toHaveCount(0)

        await page.mouse.up()
        expect(await selects(page)).toEqual([])
    })

    test('is the selection\'s, and does not reach what is around the calendar', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => {
            window.__escapes = 0
            document.addEventListener('keydown', (event) => event.key === 'Escape' && window.__escapes++)
        })

        const from = await inDay(page, '2026-10-12')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 200, from.y, { steps: 4 })
        await page.keyboard.press('Escape')
        await page.mouse.up()

        expect(await page.evaluate(() => window.__escapes)).toBe(0)

        // with nothing being made it is the page's again
        await page.keyboard.press('Escape')
        expect(await page.evaluate(() => window.__escapes)).toBe(1)
    })

    test('takes down a selection that was made and is not held', async ({ page }) => {
        await open(page, 'selectable=1')

        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page)).toHaveCount(1)

        await page.keyboard.press('Escape')
        await expect(boxes(page)).toHaveCount(0)
    })
})

test.describe('what a selection that is not held waits for', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'selectable=1')
        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page)).toHaveCount(1)
    })

    test('it stays through a render that changes nothing the calendar draws', async ({ page }) => {
        // called, not clicked: a press on the page's button would take the selection down by itself
        await page.evaluate(() => window.Livewire.first().bump())
        await expect(page.locator('[data-renders]')).toHaveText('1')

        await expect(boxes(page)).toHaveCount(1)
    })

    test('it goes with the next payload, the host\'s answer', async ({ page }) => {
        await page.evaluate(() => window.Livewire.first().toggleExtra())

        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Added later' })).toBeVisible()
        await expect(boxes(page)).toHaveCount(0)
    })

    test('it stays through a payload attribute that is written again as it was, which is no new payload', async ({ page }) => {
        await page.evaluate(() => {
            const root = document.querySelector('[data-atom-calendar]')

            root.setAttribute('data-atom-calendar-payload', root.getAttribute('data-atom-calendar-payload'))
        })
        await page.waitForTimeout(150)

        await expect(boxes(page)).toHaveCount(1)
    })

    test('it goes when something else on the page is pressed', async ({ page }) => {
        await page.locator('body').click({ position: { x: 5, y: 5 } })

        await expect(boxes(page)).toHaveCount(0)
    })

    test('it goes when the next one is made, and the new one is what is on screen', async ({ page }) => {
        await click(page, await inDay(page, '2026-10-15'))

        await expect(boxes(page)).toHaveCount(1)
        expect((await engine(page)).preview).toMatchObject({ kind: 'select', start: '2026-10-15' })
        expect(await selects(page)).toHaveLength(2)
    })

    test('it goes with a step to another month, and with a change of view', async ({ page }) => {
        // called, not clicked: a press on a button would take the selection down by itself
        const call = (method, ...args) => page.evaluate(([name, params]) => window.Alpine.$data(document.querySelector('[data-atom-calendar]'))[name](...params), [method, args])

        await call('setView', 'week')
        expect((await engine(page)).preview).toBeNull()

        await call('setView', 'month')
        await expect(cell(page, '2026-10-12')).toBeVisible()
        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page)).toHaveCount(1)

        await call('next')
        await expect(page.locator('[data-atom-calendar-title]')).toContainText('November')
        await expect(boxes(page)).toHaveCount(0)
    })
})

test.describe('waitUntil', () => {
    test('a promise holds a placeholder, with a title and a spinner, where the selection was, until it is settled; and a payload does not take it down', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => { window.__selectMode = 'wait' })

        await drag(page, await inDay(page, '2026-10-12'), await inDay(page, '2026-10-14'))

        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')
        await expect(ghosts(page)).toHaveCount(1)
        await expect(ghosts(page)).toContainText('Creating...')
        await expect(ghosts(page).locator('svg')).toHaveCount(1)

        // over the three days
        const first = await cell(page, '2026-10-12').boundingBox()
        const box = await boxes(page).boundingBox()

        expect(Math.abs(box.width - first.width * 3)).toBeLessThan(3)

        // a payload comes (the host re-rendered with more), and the placeholder is still there
        await page.locator('[data-toggle-extra]').click()
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Added later' })).toBeVisible()
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        // a press elsewhere does not take it down either: the host is not done
        await page.locator('body').click({ position: { x: 5, y: 5 } })
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        await page.evaluate(() => window.__settle.resolve())
        await expect(boxes(page)).toHaveCount(0)
        await expect(ghosts(page)).toHaveCount(0)
        expect((await engine(page)).preview).toBeNull()
    })

    test('a promise that is rejected takes the placeholder down as well', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => { window.__selectMode = 'reject' })

        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        await page.evaluate(() => window.__settle.reject(new Error('refused')))
        await expect(boxes(page)).toHaveCount(0)
        await expect(ghosts(page)).toHaveCount(0)
    })

    test('the host has the range in the format it can hand to fromLocal(), and what it created comes back with the payload, the placeholder going after it', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => { window.__selectMode = 'livewire' })

        await drag(page, await inDay(page, '2026-10-12'), await inDay(page, '2026-10-14'))
        await expect(page.locator('[data-created]')).toContainText('created-1')

        // an all-day range is dates, the last day included; the server read them as local days of Kuala Lumpur (UTC+8): midnight there is 16:00 of the day before
        expect(JSON.parse(await page.locator('[data-created]').textContent())).toEqual([{
            id: 'created-1',
            start: '2026-10-12',
            end: '2026-10-14',
            allDay: true,
            startUtc: '2026-10-11T16:00:00Z',
            endUtc: '2026-10-13T16:00:00Z',
        }])

        const bar = probe(page).locator('[data-atom-calendar-chip="bar"]', { hasText: 'Created' })

        await expect(bar).toHaveCount(1)
        await expect(boxes(page)).toHaveCount(0)
        await expect(ghosts(page)).toHaveCount(0)

        const first = await cell(page, '2026-10-12').boundingBox()
        const box = await bar.boundingBox()

        expect(box.x).toBeLessThan(first.x + 12)
        expect(Math.abs(box.width - first.width * 3)).toBeLessThan(14)
    })

    test('a promise given after the event has been handled holds nothing', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => {
            document.addEventListener('calendar-select', (event) => { window.__late = event.detail.waitUntil })
        })

        await click(page, await inDay(page, '2026-10-12'))
        await page.evaluate(() => window.__late(new Promise(() => {})))

        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'select')
    })

    test('a promise may be several: the placeholder stays until the last of them has settled', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => {
            window.__gates = []
            document.addEventListener('calendar-select', (event) => {
                for (let n = 0; n < 2; n++) event.detail.waitUntil(new Promise((resolve) => window.__gates.push(resolve)))
            })
        })

        await click(page, await inDay(page, '2026-10-12'))
        await page.evaluate(() => window.__gates[0]())
        await page.waitForTimeout(100)
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        await page.evaluate(() => window.__gates[1]())
        await expect(boxes(page)).toHaveCount(0)
    })

    test('a new selection made while one waits is the one on screen, and the settling of the first does not take it down', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => {
            window.__gates = []
            window.__selectMode = 'none'
            document.addEventListener('calendar-select', (event) => {
                if (window.__gates.length === 0) event.detail.waitUntil(new Promise((resolve) => window.__gates.push(resolve)))
            })
        })

        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        await click(page, await inDay(page, '2026-10-15'))
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'select')

        await page.evaluate(() => window.__gates[0]())
        await page.waitForTimeout(100)

        await expect(boxes(page)).toHaveCount(1)
        expect((await engine(page)).preview).toMatchObject({ kind: 'select', start: '2026-10-15' })
    })
})

test.describe('waitUntil and a selection in the making', () => {
    test('the settling of an earlier promise does not take down the selection that is being made over it', async ({ page }) => {
        await open(page, 'selectable=1')
        await page.evaluate(() => {
            window.__gates = []
            document.addEventListener('calendar-select', (event) => {
                if (window.__gates.length === 0) event.detail.waitUntil(new Promise((resolve) => window.__gates.push(resolve)))
            })
        })

        await click(page, await inDay(page, '2026-10-12'))
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'pending')

        const from = await inDay(page, '2026-10-15')
        const to = await inDay(page, '2026-10-17')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 5 })
        await expect(boxes(page).first()).toHaveAttribute('data-kind', 'select')

        // the host answers the first while the pointer is still down
        await page.evaluate(() => window.__gates[0]())
        await page.waitForTimeout(150)

        await expect(boxes(page)).toHaveCount(1)
        expect((await engine(page)).preview).toMatchObject({ kind: 'select', start: '2026-10-15', end: '2026-10-17' })

        await page.mouse.up()
        expect((await selects(page)).at(-1)).toEqual({ start: '2026-10-15', end: '2026-10-17', allDay: true })
    })
})

test.describe('while a selection is being made and a payload comes', () => {
    test('in the month: the selection is not dropped or changed, and the range is what the pointer ends on', async ({ page }) => {
        await open(page, 'selectable=1')

        const from = await inDay(page, '2026-10-12')
        const middle = await inDay(page, '2026-10-14')
        const to = await inDay(page, '2026-10-16')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(middle.x, middle.y, { steps: 5 })

        // the host re-renders with another item while the pointer is down
        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Added later' })).toBeVisible()

        await expect(boxes(page)).toHaveCount(1)
        expect((await engine(page)).gesture).toBe(true)

        const first = await cell(page, '2026-10-12').boundingBox()

        expect(Math.abs((await boxes(page).boundingBox()).width - first.width * 3)).toBeLessThan(3)

        await page.mouse.move(to.x, to.y, { steps: 5 })
        await page.mouse.up()

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-16', allDay: true }])
    })

    test('in the time grid: the same', async ({ page }) => {
        await open(page, 'selectable=1&view=week&date=2026-10-12')

        const from = await at(page, '2026-10-13', 9)
        const middle = await at(page, '2026-10-13', 10)
        const to = await at(page, '2026-10-13', 11.25)

        await page.mouse.move(from.x, from.y + 4)
        await page.mouse.down()
        await page.mouse.move(middle.x, middle.y, { steps: 5 })

        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await expect(page.locator('[data-renders]')).toHaveText('0')
        await page.waitForTimeout(300)

        await expect(shapes(page)).toHaveCount(1)
        expect((await engine(page)).gesture).toBe(true)

        await page.mouse.move(to.x, to.y, { steps: 5 })
        await page.mouse.up()

        expect(await selects(page)).toEqual([{ start: '2026-10-13T09:00', end: '2026-10-13T11:30', allDay: false }])
    })
})

test.describe('the time grid', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'selectable=1&view=week&date=2026-10-12')
    })

    test('a click on a slot selects it: half an hour from its start, in the format a time has', async ({ page }) => {
        // 10:10 is in the 10:00 slot
        await click(page, await at(page, '2026-10-13', 10 + 10 / 60))

        expect(await selects(page)).toEqual([{ start: '2026-10-13T10:00', end: '2026-10-13T10:30', allDay: false }])

        await expect(shapes(page)).toHaveCount(1)
        await expect(shapes(page)).toContainText('10:00 AM – 10:30 AM')

        // where it is drawn: from 10:00 for half an hour of a day, in its own column
        const box = await shapes(page).boundingBox()
        const col = await column(page, '2026-10-13').boundingBox()

        expect(Math.abs((box.y - col.y) / col.height - 10 / 24)).toBeLessThan(0.002)
        expect(Math.abs(box.height / col.height - 0.5 / 24)).toBeLessThan(0.002)
        expect(Math.abs(box.width - col.width)).toBeLessThan(4)
    })

    test('a drag over slots selects from the slot it started in to the slot it ends in, that one included', async ({ page }) => {
        await drag(page, await at(page, '2026-10-13', 9 + 0.1), await at(page, '2026-10-13', 11.25))

        expect(await selects(page)).toEqual([{ start: '2026-10-13T09:00', end: '2026-10-13T11:30', allDay: false }])
        await expect(shapes(page)).toContainText('9:00 AM – 11:30 AM')

        const box = await shapes(page).boundingBox()
        const col = await column(page, '2026-10-13').boundingBox()

        expect(Math.abs((box.y - col.y) / col.height - 9 / 24)).toBeLessThan(0.002)
        expect(Math.abs(box.height / col.height - 2.5 / 24)).toBeLessThan(0.002)
    })

    test('upwards is the same range', async ({ page }) => {
        await drag(page, await at(page, '2026-10-13', 11.25), await at(page, '2026-10-13', 9.1))

        expect(await selects(page)).toEqual([{ start: '2026-10-13T09:00', end: '2026-10-13T11:30', allDay: false }])
    })

    test('a drag across columns is one range of time from the first slot to the last, drawn in each column it runs over', async ({ page }) => {
        const from = await at(page, '2026-10-12', 10.1)
        const to = await at(page, '2026-10-14', 11.1)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 8 })

        // while it is down: Monday from 10:00 to the end of the day, Tuesday whole, Wednesday to 11:30
        await expect(shapes(page)).toHaveCount(3)

        const share = async (date) => {
            const col = await column(page, date).boundingBox()
            const box = await column(page, date).locator('[data-atom-calendar-preview]').boundingBox()

            return { top: (box.y - col.y) / col.height, bottom: (box.y + box.height - col.y) / col.height }
        }

        const monday = await share('2026-10-12')
        const tuesday = await share('2026-10-13')
        const wednesday = await share('2026-10-14')

        expect(Math.abs(monday.top - 10 / 24)).toBeLessThan(0.002)
        expect(Math.abs(monday.bottom - 1)).toBeLessThan(0.002)
        expect(Math.abs(tuesday.top)).toBeLessThan(0.002)
        expect(Math.abs(tuesday.bottom - 1)).toBeLessThan(0.002)
        expect(Math.abs(wednesday.top)).toBeLessThan(0.002)
        expect(Math.abs(wednesday.bottom - 11.5 / 24)).toBeLessThan(0.002)

        await page.mouse.up()

        expect(await selects(page)).toEqual([{ start: '2026-10-12T10:00', end: '2026-10-14T11:30', allDay: false }])
    })

    test('a drag from a later column back to an earlier one is the same range', async ({ page }) => {
        await drag(page, await at(page, '2026-10-14', 11.1), await at(page, '2026-10-12', 10.1))

        expect(await selects(page)).toEqual([{ start: '2026-10-12T10:00', end: '2026-10-14T11:30', allDay: false }])
    })

    test('the last slot of the day ends at midnight of the next date', async ({ page }) => {
        await probe(page).locator('[data-atom-calendar-grid]').evaluate((el) => { el.scrollTop = el.scrollHeight })

        const col = await column(page, '2026-10-13').boundingBox()
        const grid = await probe(page).locator('[data-atom-calendar-grid]').boundingBox()

        await click(page, { x: col.x + col.width / 2, y: grid.y + grid.height - 6 })

        expect(await selects(page)).toEqual([{ start: '2026-10-13T23:30', end: '2026-10-14T00:00', allDay: false }])
    })

    test('a block is pressed on its own: it tells of its click and selects nothing', async ({ page }) => {
        await expect(probe(page).locator('[data-atom-calendar-block]', { hasText: 'Design review' })).toBeVisible()

        const block = await probe(page).locator('[data-atom-calendar-block]', { hasText: 'Design review' }).boundingBox()

        await click(page, { x: block.x + block.width / 2, y: block.y + block.height / 2 })

        expect(await page.evaluate(() => window.__clicks)).toEqual([6])
        expect(await selects(page)).toEqual([])
        await expect(shapes(page)).toHaveCount(0)
    })

    test('the header of a day, the hour labels and the gutter select nothing', async ({ page }) => {
        const head = await probe(page).locator('[data-atom-calendar-column-head]').first().boundingBox()
        const gutter = await probe(page).locator('[data-atom-calendar-grid-body] [data-atom-calendar-gutter]').boundingBox()

        await click(page, { x: head.x + head.width / 2, y: head.y + head.height / 2 })
        await click(page, { x: gutter.x + 10, y: gutter.y + 300 })

        expect(await selects(page)).toEqual([])
    })

    test('the all-day row: a click selects that day, a drag a range of days, the last included, drawn in the row', async ({ page }) => {
        await click(page, await inAllDay(page, '2026-10-14'))

        expect(await selects(page)).toEqual([{ start: '2026-10-14', end: '2026-10-14', allDay: true }])
        await expect(allDayBoxes(page)).toHaveCount(1)
        // a day's range has no part in the columns
        await expect(shapes(page)).toHaveCount(0)

        await drag(page, await inAllDay(page, '2026-10-13'), await inAllDay(page, '2026-10-15'))

        expect((await selects(page)).at(-1)).toEqual({ start: '2026-10-13', end: '2026-10-15', allDay: true })

        const area = await probe(page).locator('[data-atom-calendar-allday-area]').boundingBox()
        const box = await allDayBoxes(page).boundingBox()

        expect(Math.abs(box.width - (area.width * 3) / 7)).toBeLessThan(3)
        expect(Math.abs(box.x - (area.x + area.width / 7))).toBeLessThan(3)
    })

    test('the all-day row holds a pending placeholder with a title', async ({ page }) => {
        await page.evaluate(() => { window.__selectMode = 'wait' })

        await click(page, await inAllDay(page, '2026-10-14'))

        await expect(allDayBoxes(page)).toHaveAttribute('data-kind', 'pending')
        await expect(allDayBoxes(page)).toContainText('Creating...')

        await page.evaluate(() => window.__settle.resolve())
        await expect(allDayBoxes(page)).toHaveCount(0)
    })

    test('a promise holds a placeholder in the column: its time, a spinner and the title', async ({ page }) => {
        await page.evaluate(() => { window.__selectMode = 'wait' })

        await drag(page, await at(page, '2026-10-13', 9.1), await at(page, '2026-10-13', 10.1))

        await expect(shapes(page)).toHaveAttribute('data-kind', 'pending')
        await expect(shapes(page)).toContainText('Creating...')
        await expect(shapes(page).locator('svg')).toHaveCount(1)

        await page.evaluate(() => window.__settle.reject(new Error('refused')))
        await expect(shapes(page)).toHaveCount(0)
    })

    test('the host creates it: the item comes back at the time that was selected, and the placeholder goes after it', async ({ page }) => {
        await page.evaluate(() => { window.__selectMode = 'livewire' })

        await drag(page, await at(page, '2026-10-13', 9.1), await at(page, '2026-10-13', 10.1))
        await expect(page.locator('[data-created]')).toContainText('created-1')

        // the server took the local time as Kuala Lumpur's (UTC+8)
        expect(JSON.parse(await page.locator('[data-created]').textContent())).toEqual([{
            id: 'created-1',
            start: '2026-10-13T09:00',
            end: '2026-10-13T10:30',
            allDay: false,
            startUtc: '2026-10-13T01:00:00Z',
            endUtc: '2026-10-13T02:30:00Z',
        }])

        const block = probe(page).locator('[data-atom-calendar-block]', { hasText: 'Created' })

        await expect(block).toHaveCount(1)
        await expect(shapes(page)).toHaveCount(0)

        const box = await block.boundingBox()
        const col = await column(page, '2026-10-13').boundingBox()

        expect(Math.abs((box.y - col.y) / col.height - 9 / 24)).toBeLessThan(0.003)
        expect(Math.abs(box.height / col.height - 1.5 / 24)).toBeLessThan(0.003)
    })

    test('the day view has one column, and selects in it', async ({ page }) => {
        await probe(page).getByRole('button', { name: 'Day', exact: true }).click()
        await expect(probe(page).locator('[data-atom-calendar-column]')).toHaveCount(1)

        const date = await probe(page).locator('[data-atom-calendar-column]').getAttribute('data-date')

        await drag(page, await at(page, date, 9.1), await at(page, date, 10.1))

        expect(await selects(page)).toEqual([{ start: date + 'T09:00', end: date + 'T10:30', allDay: false }])
    })
})

test.describe('a viewer\'s own week and slots', () => {
    test('a week that starts on a Sunday: a drag in the month is the days under the pointer, not the days a Monday week would put there', async ({ page }) => {
        await open(page, 'selectable=1&weekStart=sunday')

        await expect(probe(page).locator('[data-atom-calendar-day]').first()).toHaveAttribute('data-date', '2026-09-27')

        await drag(page, await inDay(page, '2026-10-11'), await inDay(page, '2026-10-13'))

        expect(await selects(page)).toEqual([{ start: '2026-10-11', end: '2026-10-13', allDay: true }])

        // the first column of a week is Sunday: a click in the first column of the row of the 11th is the 11th
        const sunday = await cell(page, '2026-10-11').boundingBox()
        const grid = await probe(page).locator('[data-atom-calendar-weeks]').boundingBox()

        expect(Math.abs(sunday.x - grid.x)).toBeLessThan(2)
    })

    test('a week that starts on a Sunday: in the time grid the first column is the Sunday', async ({ page }) => {
        await open(page, 'selectable=1&weekStart=sunday&view=week&date=2026-10-12')

        await expect(probe(page).locator('[data-atom-calendar-column]').first()).toHaveAttribute('data-date', '2026-10-11')

        await drag(page, await at(page, '2026-10-11', 9.1), await at(page, '2026-10-12', 10.1))

        expect(await selects(page)).toEqual([{ start: '2026-10-11T09:00', end: '2026-10-12T10:30', allDay: false }])
    })

    for (const [slot, start, end] of [[15, '10:15', '10:30'], [30, '10:00', '10:30'], [60, '10:00', '11:00'], [5, '10:20', '10:25']]) {
        test(`a click at 10:22 in a grid of ${slot} minute slots selects ${start} to ${end}`, async ({ page }) => {
            await open(page, `selectable=1&view=week&slot=${slot}&scroll=08:00`, '/atom/e2e/calendar-grid')

            await click(page, await at(page, '2026-10-10', 10 + 22 / 60, 'grid'))

            expect(await selects(page)).toEqual([{ start: `2026-10-10T${start}`, end: `2026-10-10T${end}`, allDay: false }])
        })
    }

    test('a drag in slots of an hour is whole hours', async ({ page }) => {
        await open(page, 'selectable=1&view=week&slot=60&scroll=08:00', '/atom/e2e/calendar-grid')

        await drag(page, await at(page, '2026-10-10', 9.2, 'grid'), await at(page, '2026-10-10', 11.7, 'grid'))

        expect(await selects(page)).toEqual([{ start: '2026-10-10T09:00', end: '2026-10-10T12:00', allDay: false }])
    })
})

// The wall clock in the details is the viewer's, however the browser's own clock is set: nothing is converted in the browser
for (const timezoneId of ['America/Los_Angeles', 'Pacific/Kiritimati']) {
    test.describe(`in a browser in ${timezoneId}`, () => {
        test.use({ timezoneId })

        test('a day, a range of days, a slot and a range across a night are what the grid says', async ({ page }) => {
            await open(page, 'selectable=1')

            await click(page, await inDay(page, '2026-10-12'))
            await drag(page, await inDay(page, '2026-10-15'), await inDay(page, '2026-10-20'))

            expect(await selects(page)).toEqual([
                { start: '2026-10-12', end: '2026-10-12', allDay: true },
                { start: '2026-10-15', end: '2026-10-20', allDay: true },
            ])

            await open(page, 'selectable=1&view=week&date=2026-10-12')

            await click(page, await at(page, '2026-10-13', 10.1))
            await drag(page, await at(page, '2026-10-13', 9.1), await at(page, '2026-10-14', 8.6))

            expect(await selects(page)).toEqual([
                { start: '2026-10-13T10:00', end: '2026-10-13T10:30', allDay: false },
                { start: '2026-10-13T09:00', end: '2026-10-14T09:00', allDay: false },
            ])
        })

        test('what the host makes of it is the viewer\'s Kuala Lumpur time, not the browser\'s', async ({ page }) => {
            await open(page, 'selectable=1&view=week&date=2026-10-12')
            await page.evaluate(() => { window.__selectMode = 'livewire' })

            await click(page, await at(page, '2026-10-13', 10.1))
            await expect(page.locator('[data-created]')).toContainText('created-1')

            expect(JSON.parse(await page.locator('[data-created]').textContent())[0]).toMatchObject({ start: '2026-10-13T10:00', startUtc: '2026-10-13T02:00:00Z' })
        })
    })
}

test.describe('a touch', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'selectable=1')
    })

    test('a tap selects the day', async ({ page }) => {
        const point = await inDay(page, '2026-10-12')

        await pointer(page, 'pointerdown', point)
        await pointer(page, 'pointerup', point)

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-12', allDay: true }])
    })

    test('a finger that moves before the long press is scrolling: nothing is selected and the page may scroll', async ({ page }) => {
        const from = await inDay(page, '2026-10-12')
        const to = { x: from.x + 3, y: from.y - 40 }

        await pointer(page, 'pointerdown', from)
        await pointer(page, 'pointermove', to)

        expect(await touchMoveAllowed(page, to)).toBe(true)

        // the long press that was waiting does not come
        await page.waitForTimeout(700)
        await expect(boxes(page)).toHaveCount(0)

        await pointer(page, 'pointerup', to)
        expect(await selects(page)).toEqual([])
    })

    test('a long press starts the selection, and from then on the finger is the selection\'s: the page may not scroll under it', async ({ page }) => {
        const from = await inDay(page, '2026-10-12')
        const to = await inDay(page, '2026-10-15')

        await pointer(page, 'pointerdown', from)

        // not yet: before the press is long, the touch is a touch
        await expect(boxes(page)).toHaveCount(0)
        expect(await touchMoveAllowed(page, from)).toBe(true)

        await expect(boxes(page)).toHaveCount(1, { timeout: 1500 })
        expect(await touchMoveAllowed(page, from)).toBe(false)

        await pointer(page, 'pointermove', to)
        await pointer(page, 'pointerup', to)

        expect(await selects(page)).toEqual([{ start: '2026-10-12', end: '2026-10-15', allDay: true }])
    })

    test('a long press gives way to Escape, and to the browser taking the touch', async ({ page }) => {
        const from = await inDay(page, '2026-10-12')

        await pointer(page, 'pointerdown', from)
        await expect(boxes(page)).toHaveCount(1, { timeout: 1500 })
        await pointer(page, 'pointercancel', from)

        await pointer(page, 'pointerup', from)
        expect(await selects(page)).toEqual([])
        expect((await engine(page)).gesture).toBe(false)
    })

    test('the touch of another finger is not this one\'s', async ({ page }) => {
        const from = await inDay(page, '2026-10-12')

        await pointer(page, 'pointerdown', from, 'touch', 7)
        await pointer(page, 'pointerup', await inDay(page, '2026-10-20'), 'touch', 8)

        expect(await selects(page)).toEqual([])
        expect((await engine(page)).gesture).toBe(true)
    })
})

test.describe('the time grid on a touch', () => {
    test('a tap selects the slot, and a long press then a move selects a range of slots', async ({ page }) => {
        await open(page, 'selectable=1&view=week&date=2026-10-12')

        const tap = await at(page, '2026-10-13', 10.1)

        await pointer(page, 'pointerdown', tap)
        await pointer(page, 'pointerup', tap)

        expect(await selects(page)).toEqual([{ start: '2026-10-13T10:00', end: '2026-10-13T10:30', allDay: false }])

        const from = await at(page, '2026-10-13', 9.1)
        const to = await at(page, '2026-10-13', 11.1)

        await pointer(page, 'pointerdown', from)
        await expect(shapes(page)).toHaveCount(1, { timeout: 1500 })
        await pointer(page, 'pointermove', to)
        await pointer(page, 'pointerup', to)

        expect((await selects(page)).at(-1)).toEqual({ start: '2026-10-13T09:00', end: '2026-10-13T11:30', allDay: false })
    })
})
