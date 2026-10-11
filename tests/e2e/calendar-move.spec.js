import { test, expect } from '@playwright/test'

// Moving and resizing an item, in a browser: a drag of a chip in the month, of a bar in the all-day row and of a block in the time grid, and a pull
// of the handle on its end; and what the host is told (`calendar-item-move`, `calendar-item-resize`, in the viewer's wall clock) and when. Real
// mouse events at points measured before anything moves.
//
// Two pages, as in the selection's spec: the Livewire fixture (/atom/e2e/calendar, frozen at 2026-10-09 10:30 in Kuala Lumpur, so October's window is
// 2026-09-28 to 2026-11-08) with a host script that records what it is told (window.__moves, window.__resizes) and answers by window.__itemMode,
// and the static grid page (/atom/e2e/calendar-grid) for a viewer's own slots and timezone and for the items' own flags. The rig has no Tailwind:
// what is measured here is what atom.css owns, the hour height (3.5rem) and the week's columns.

const HOUR = 3.5

const probe = (page, name = 'calendar') => page.locator(`[data-probe="${name}"]`)
const cell = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-day][data-date="${date}"]`)
const column = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-column][data-date="${date}"]`)
const allDayCell = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-allday-bg] > [data-date="${date}"]`)
const ghosts = (page, name) => probe(page, name).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')
const shapes = (page, name) => probe(page, name).locator('[data-atom-calendar-column] [data-atom-calendar-preview]')
const allDayGhosts = (page, name) => probe(page, name).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-ghost]')
// what is shown: a calendar that has a view or two hidden has their items in the page too
const chipOf = (page, kind, text, name) => probe(page, name).locator(`[data-atom-calendar-chip="${kind}"]:visible`, { hasText: text })
const handleOf = (page, kind, text, name) => chipOf(page, kind, text, name).locator('[data-atom-calendar-handle]:visible')
const grid = (page, name) => probe(page, name).locator('[data-atom-calendar-grid]')

async function open (page, query = '', path = '/atom/e2e/calendar') {
    await page.goto(path + (query ? '?' + query : ''))
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)

    await page.evaluate(() => {
        window.__clicks = []
        window.__errors = []
        document.addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail.id))
        window.addEventListener('error', (event) => window.__errors.push(event.message))
    })
}

const moves = (page) => page.evaluate(() => window.__moves)
const resizes = (page) => page.evaluate(() => window.__resizes)
const mode = (page, value) => page.evaluate((next) => { window.__itemMode = next }, value)
const engine = (page, index = 0) => page.evaluate((at) => {
    const { preview, gesture, payload, overrides } = window.Alpine.$data(document.querySelectorAll('[data-atom-calendar]')[at])

    return { preview: preview ? { ...preview } : null, gesture: gesture !== null, items: payload.items.length, overrides: Object.keys(overrides) }
}, index)
const rescheduled = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.Livewire.first().rescheduled)))
const payloadArrived = (page, text) => page.waitForFunction((needle) => document.querySelector('[data-atom-calendar]').getAttribute('data-atom-calendar-payload').includes(needle), text)

// the day of the month grid a point is in
const dateAt = (page, point, name = 'calendar') => page.evaluate(([x, y, probeName]) => {
    const day = [...document.querySelectorAll(`[data-probe="${probeName}"] [data-atom-calendar-day]`)].find((el) => {
        const box = el.getBoundingClientRect()

        return x >= box.left && x < box.right && y >= box.top && y < box.bottom
    })

    return day?.dataset.date ?? null
}, [point.x, point.y, name])

// the day an item's chip starts on: the day under its left edge
async function startsOn (page, locator, name) {
    const box = await locator.first().boundingBox()

    return dateAt(page, { x: box.x + 4, y: box.y + box.height / 2 }, name)
}

// the middle of a chip, and the middle of a day low in its cell (clear of the day number and the chips)
async function middle (locator) {
    const box = await locator.first().boundingBox()

    return { x: box.x + box.width / 2, y: box.y + box.height / 2 }
}

// what an hour of the time grid is tall, as drawn (3.5 rems, and a rem is what the page says)
const hourPx = async (page, name) => (await probe(page, name).locator('[data-atom-calendar-column]:visible').first().boundingBox()).height / 24

// a point a little under the top of an item, which is not on a boundary between two slots, as the middle of an item of whole hours is
async function near (locator, px = 8) {
    const box = await locator.first().boundingBox()

    return { x: box.x + box.width / 2, y: box.y + px }
}

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

// the same x as an all-day cell, at the height of a bar
async function inAllDay (page, date, like, name) {
    const box = await allDayCell(page, date, name).boundingBox()
    const bar = await like.boundingBox()

    return { x: box.x + box.width / 2, y: bar.y + bar.height / 2 }
}

async function drag (page, from, ...through) {
    await page.mouse.move(from.x, from.y)
    await page.mouse.down()

    for (const point of through) await page.mouse.move(point.x, point.y, { steps: 8 })

    await page.mouse.up()
}

// the part of a day that is on screen: the grid is a scroller, and it opens at 06:00
const scrollGrid = (page, top, name) => grid(page, name).evaluate((el, value) => { el.scrollTop = value }, top)
const scrollTop = (page, name) => grid(page, name).evaluate((el) => el.scrollTop)

// a pointer of the given kind, on the page, as the browser sends one
const pointer = (page, type, point, pointerType = 'touch', id = 7) => page.evaluate(([kind, x, y, pointerKind, pointerId]) => {
    const target = document.elementFromPoint(x, y) ?? document.body

    target.dispatchEvent(new PointerEvent(kind, { pointerId, pointerType: pointerKind, isPrimary: true, button: 0, buttons: kind === 'pointerup' ? 0 : 1, bubbles: true, cancelable: true, composed: true, clientX: x, clientY: y }))
}, [type, point.x, point.y, pointerType, id])

// whether the page may scroll under a finger that moves now: false when the calendar has prevented the touchmove
const touchMoveAllowed = (page, point) => page.evaluate(([x, y]) => {
    const target = document.elementFromPoint(x, y)
    const event = new Event('touchmove', { bubbles: true, cancelable: true })

    target.dispatchEvent(event)

    return !event.defaultPrevented
}, [point.x, point.y])

test.describe('an item in the month', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('a timed item dragged to another day keeps its time of day and its length, and the host is told once, in the format a time has', async ({ page }) => {
        const standup = chipOf(page, 'dot', 'Team standup')
        const from = await middle(standup)
        const to = await inDay(page, '2026-10-12')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 8 })

        // while the pointer is down: its ghost is on the day, the original is lifted, and nobody is told
        await expect(ghosts(page)).toHaveCount(1)
        await expect(ghosts(page)).toContainText('Team standup')
        await expect(standup).toHaveAttribute('data-lifted', '')
        expect(await moves(page)).toEqual([])

        const day = await cell(page, '2026-10-12').boundingBox()
        const ghost = await ghosts(page).boundingBox()

        expect(ghost.x).toBeGreaterThan(day.x - 2)
        expect(ghost.x + ghost.width).toBeLessThan(day.x + day.width + 4)

        await page.mouse.up()

        expect(await moves(page)).toEqual([{ id: 1, start: '2026-10-12T09:30', end: '2026-10-12T10:00', allDay: false }])
        expect(await resizes(page)).toEqual([])
        await expect(ghosts(page)).toHaveCount(0)

        // the item is where it was put, with no render: the host did nothing about it
        expect(await startsOn(page, chipOf(page, 'dot', 'Team standup'))).toBe('2026-10-12')
        await expect(chipOf(page, 'dot', 'Team standup')).not.toHaveAttribute('data-lifted', '')
        expect((await engine(page)).overrides).toEqual(['1'])
    })

    test('an all-day bar moves by the days the pointer moved, whole, and the end is the last day, included', async ({ page }) => {
        const launch = chipOf(page, 'bar', 'Launch week')
        const box = await launch.boundingBox()

        // grabbed on Wednesday, put down on the Wednesday after
        await drag(page, await dateGrab(page, launch, '2026-10-07'), await inDay(page, '2026-10-14'))

        expect(await moves(page)).toEqual([{ id: 2, start: '2026-10-12', end: '2026-10-16', allDay: true }])

        // five days wide still, on the week of the 12th
        const moved = await chipOf(page, 'bar', 'Launch week').boundingBox()

        expect(Math.abs(moved.width - box.width)).toBeLessThan(3)
        expect(await startsOn(page, chipOf(page, 'bar', 'Launch week'))).toBe('2026-10-12')
    })

    test('a bar over two weeks is moved by where it is held, not by the part of it that is under the pointer', async ({ page }) => {
        // Conference is 2 to 6 October: the 2nd to the 4th in the first week, the 5th and the 6th in the second
        const conference = chipOf(page, 'bar', 'Conference')

        await drag(page, await dateGrab(page, conference.nth(1), '2026-10-05'), await inDay(page, '2026-10-12'))
        expect(await moves(page)).toEqual([{ id: 3, start: '2026-10-09', end: '2026-10-13', allDay: true }])

        await open(page)
        await drag(page, await dateGrab(page, chipOf(page, 'bar', 'Conference').first(), '2026-10-03'), await inDay(page, '2026-10-10'))
        expect(await moves(page)).toEqual([{ id: 3, start: '2026-10-09', end: '2026-10-13', allDay: true }])
    })

    test('a bar that runs over a number of days and carries on past the week has no handle where it carries on', async ({ page }) => {
        // on the part that has the end of the item (the 5th and the 6th), and not on the other
        await expect(handleOf(page, 'bar', 'Conference')).toHaveCount(1)
        await expect(chipOf(page, 'bar', 'Conference').nth(0).locator('[data-atom-calendar-handle]')).toBeHidden()
        await expect(chipOf(page, 'bar', 'Conference').nth(1).locator('[data-atom-calendar-handle]')).toBeVisible()
    })

    test('the end of an all-day bar is pulled to another day: the end changes, the start stays, and the host is told of a resize and not of a move', async ({ page }) => {
        const handle = handleOf(page, 'bar', 'Launch week')
        const box = await handle.boundingBox()

        // a few px wide, on the end of the bar
        expect(box.width).toBeLessThan(8)

        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
        await page.mouse.down()
        await page.mouse.move((await inDay(page, '2026-10-12')).x, (await inDay(page, '2026-10-12')).y, { steps: 8 })

        await expect(ghosts(page).first()).toHaveAttribute('data-kind', 'resize')
        expect(await resizes(page)).toEqual([])

        await page.mouse.up()

        expect(await resizes(page)).toEqual([{ id: 2, start: '2026-10-05', end: '2026-10-12' }])
        expect(await moves(page)).toEqual([])

        // from the 5th to the 12th now, over two weeks
        await expect(chipOf(page, 'bar', 'Launch week')).toHaveCount(2)
    })

    test('a bar cannot be pulled to end before it starts: it covers its first day at least', async ({ page }) => {
        const handle = handleOf(page, 'bar', 'Launch week')

        await drag(page, await middle(handle), await inDay(page, '2026-10-02'))

        expect(await resizes(page)).toEqual([{ id: 2, start: '2026-10-05', end: '2026-10-05' }])
    })

    test('a timed item over days: a move keeps the times, and a pull of its end changes the day it ends on and not its time', async ({ page }) => {
        // Night shift: 22 October 22:00 to 23 October 02:00, a bar in the month
        const night = chipOf(page, 'bar', 'Night shift')

        await drag(page, await dateGrab(page, night, '2026-10-22'), await inDay(page, '2026-10-26'))
        expect(await moves(page)).toEqual([{ id: 12, start: '2026-10-26T22:00', end: '2026-10-27T02:00', allDay: false }])

        await open(page)
        await drag(page, await middle(handleOf(page, 'bar', 'Night shift')), await inDay(page, '2026-10-25'))
        expect(await resizes(page)).toEqual([{ id: 12, start: '2026-10-22T22:00', end: '2026-10-25T02:00' }])
    })

    test('a timed item pulled to end before it starts is one slot long', async ({ page }) => {
        await drag(page, await middle(handleOf(page, 'bar', 'Night shift')), await inDay(page, '2026-10-20'))

        expect(await resizes(page)).toEqual([{ id: 12, start: '2026-10-22T22:00', end: '2026-10-22T22:30' }])
    })

    test('a dot has no end to pull, and neither has an item in the day list', async ({ page }) => {
        await expect(handleOf(page, 'dot', 'Team standup')).toHaveCount(0)

        // the day's list, from the keyboard: its cell has the chips over it
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await expect(probe(page).locator('[data-atom-calendar-popover]')).toBeVisible()

        const row = probe(page).locator('[data-atom-calendar-popover] [data-atom-calendar-chip="bar"]').first()

        await expect(row.locator('[data-atom-calendar-handle]')).toBeHidden()
        await expect(row).not.toHaveAttribute('data-draggable', '')

        // and a drag of it moves nothing
        const from = await middle(row)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 40, from.y + 40, { steps: 5 })
        expect((await engine(page)).gesture).toBe(false)
        await page.mouse.up()
        expect(await moves(page)).toEqual([])
    })
})

// where an item is held: the middle of the part of a bar that is on a day, found from the day's own cell
async function dateGrab (page, chip, date) {
    const day = await cell(page, date).boundingBox()
    const box = await chip.boundingBox()

    return { x: day.x + day.width / 2, y: box.y + box.height / 2 }
}

test.describe('a click is still a click', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('a press that is let go where it was is the item\'s click, and moves nothing', async ({ page }) => {
        await chipOf(page, 'dot', 'Team standup').click()

        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
        expect(await moves(page)).toEqual([])
        expect((await engine(page)).overrides).toEqual([])
    })

    test('so is one that moved less than the threshold, 5px', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 3, from.y + 1, { steps: 2 })

        expect((await engine(page)).preview).toBeNull()
        await expect(ghosts(page)).toHaveCount(0)

        await page.mouse.up()

        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
        expect(await moves(page)).toEqual([])
    })

    test('and one that moved further is a drag: it is not a click, though it is let go over the item it began on', async ({ page }) => {
        const standup = chipOf(page, 'dot', 'Team standup')
        const from = await middle(standup)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 220, from.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)
        await page.mouse.move(from.x, from.y, { steps: 6 })
        await page.mouse.up()

        // back where it was: nothing to move, and nothing opened
        expect(await moves(page)).toEqual([])
        expect(await page.evaluate(() => window.__clicks)).toEqual([])
        await expect(ghosts(page)).toHaveCount(0)
    })

    test('a link that is dragged to another day does not open', async ({ page }) => {
        // Open the docs: 21 October, a link
        const docs = chipOf(page, 'bar', 'Open the docs')
        const from = await middle(docs)
        const url = page.url()

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move((await inDay(page, '2026-10-22')).x, (await inDay(page, '2026-10-22')).y, { steps: 6 })
        await page.mouse.up()

        expect(await moves(page)).toEqual([{ id: 11, start: '2026-10-22', end: '2026-10-22', allDay: true }])
        await page.waitForTimeout(300)
        expect(page.url()).toBe(url)
    })

    test('and a press on a link that is let go where it was opens it', async ({ page }) => {
        await Promise.all([page.waitForURL('**/atom/docs'), chipOf(page, 'bar', 'Open the docs').click()])
    })
})

test.describe('what may be moved', () => {
    test('a calendar with draggable off moves nothing, and pulls nothing, and its items are still clicked', async ({ page }) => {
        await open(page, 'draggable=0')

        const standup = chipOf(page, 'dot', 'Team standup')

        await expect(standup).not.toHaveAttribute('data-draggable', '')
        await expect(handleOf(page, 'bar', 'Launch week')).toBeHidden()

        const from = await middle(standup)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 220, from.y, { steps: 6 })

        expect((await engine(page)).gesture).toBe(false)
        await expect(ghosts(page)).toHaveCount(0)
        await page.mouse.up()

        expect(await moves(page)).toEqual([])

        await standup.click()
        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
    })

    test('an item\'s own flags beat the calendar\'s: one that is locked stays, one with a fixed length is moved and not pulled', async ({ page }) => {
        await open(page, 'locked=1')

        const locked = chipOf(page, 'bar', 'Locked item')
        const fixed = chipOf(page, 'bar', 'Fixed length')

        await expect(locked).not.toHaveAttribute('data-draggable', '')
        await expect(locked.locator('[data-atom-calendar-handle]')).toBeHidden()
        await expect(fixed).toHaveAttribute('data-draggable', '')
        await expect(fixed.locator('[data-atom-calendar-handle]')).toBeHidden()

        await drag(page, await middle(locked), await inDay(page, '2026-10-20'))
        expect(await moves(page)).toEqual([])

        await drag(page, await middle(fixed), await inDay(page, '2026-10-20'))
        expect(await moves(page)).toEqual([{ id: 81, start: '2026-10-20', end: '2026-10-20', allDay: true }])
    })

    test('the other flags of an item in the time grid and the all-day row, and a calendar that cannot be moved or pulled at all', async ({ page }) => {
        await open(page, 'scenario=flags&date=2026-10-09&view=week', '/atom/e2e/calendar-grid')

        const block = (text) => chipOf(page, 'block', text, 'grid')

        await expect(block('Free item')).toHaveAttribute('data-draggable', '')
        await expect(handleOf(page, 'block', 'Free item', 'grid')).toBeVisible()
        await expect(block('Locked item')).not.toHaveAttribute('data-draggable', '')
        // locked is draggable false, and says nothing of resizable: it can be pulled
        await expect(handleOf(page, 'block', 'Locked item', 'grid')).toBeVisible()
        await expect(block('Fixed length')).toHaveAttribute('data-draggable', '')
        await expect(handleOf(page, 'block', 'Fixed length', 'grid')).toBeHidden()
        await expect(handleOf(page, 'bar', 'Free day', 'grid')).toBeVisible()
        await expect(handleOf(page, 'bar', 'Fixed day', 'grid')).toBeHidden()
    })

    test('the two props are two: an item that does not say takes the calendar\'s of each, so one can be moved and not pulled, or pulled and not moved', async ({ page }) => {
        await open(page, 'scenario=flags&date=2026-10-09&view=week&draggable=0', '/atom/e2e/calendar-grid')

        await expect(chipOf(page, 'block', 'Free item', 'grid')).not.toHaveAttribute('data-draggable', '')
        await expect(handleOf(page, 'block', 'Free item', 'grid')).toBeVisible()

        await open(page, 'scenario=flags&date=2026-10-09&view=week&resizable=0', '/atom/e2e/calendar-grid')

        await expect(chipOf(page, 'block', 'Free item', 'grid')).toHaveAttribute('data-draggable', '')
        await expect(handleOf(page, 'block', 'Free item', 'grid')).toBeHidden()
    })
})

test.describe('what the host does about it', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    // Hex colour: 20 October 14:00 to 15:00, alone on its day (the 9th has more than the month shows, and a payload that adds to it hides what is on it)
    async function dragHex (page, date = '2026-10-22') {
        await drag(page, await middle(chipOf(page, 'dot', 'Hex colour')), await inDay(page, date))
    }

    test('an item the host says nothing about stays where it was put until the next payload, which is the host\'s answer: here it did nothing, so it is back', async ({ page }) => {
        await dragHex(page)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')

        // the host re-renders with another item: the payload has it on the 20th still
        await page.locator('[data-toggle-extra]').click()
        await payloadArrived(page, 'Added later')

        await expect(chipOf(page, 'dot', 'Hex colour')).toBeVisible()
        await expect.poll(() => startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-20')
        expect((await engine(page)).overrides).toEqual([])
    })

    test('preventDefault() puts it back: it never shows anywhere else, and the ghost goes', async ({ page }) => {
        await mode(page, 'prevent')
        await dragHex(page)

        expect(await moves(page)).toHaveLength(1)
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-20')
        await expect(ghosts(page)).toHaveCount(0)
        await expect(chipOf(page, 'dot', 'Hex colour')).not.toHaveAttribute('data-lifted', '')
        expect((await engine(page)).overrides).toEqual([])
    })

    test('preventDefault() puts a resize back too', async ({ page }) => {
        await mode(page, 'prevent')
        await drag(page, await middle(handleOf(page, 'bar', 'Launch week')), await inDay(page, '2026-10-22'))

        expect(await resizes(page)).toHaveLength(1)
        await expect(chipOf(page, 'bar', 'Launch week')).toHaveCount(1)
        expect((await engine(page)).overrides).toEqual([])
    })

    test('waitUntil holds the item where it was put while the promise is pending, whatever payload comes; settled, the payload is what is shown', async ({ page }) => {
        await mode(page, 'wait')
        await dragHex(page)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')

        // a payload comes (the host re-rendered with more, and not yet with the move): the item is still where it was put
        await page.locator('[data-toggle-extra]').click()
        await payloadArrived(page, 'Added later')
        await expect(chipOf(page, 'bar', 'Added later')).toBeVisible()
        await page.waitForTimeout(200)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')
        expect((await engine(page)).overrides).toEqual(['10'])

        // resolved, and a payload has come since: that payload is the host's answer, and it says the 9th
        await page.evaluate(() => window.__settle.resolve())
        await expect.poll(() => startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-20')
        expect((await engine(page)).overrides).toEqual([])
    })

    test('a promise that is rejected puts the item back', async ({ page }) => {
        await mode(page, 'reject')
        await dragHex(page)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')

        await page.evaluate(() => window.__settle.reject(new Error('no')))
        await expect.poll(() => startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-20')
        expect((await engine(page)).overrides).toEqual([])
    })

    test('a promise that is resolved with no payload after it keeps the item where it was put: an answer that needs no render is an answer', async ({ page }) => {
        await mode(page, 'wait')
        await dragHex(page)
        await page.evaluate(() => window.__settle.resolve())
        await page.waitForTimeout(300)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')
        expect((await engine(page)).overrides).toEqual(['10'])
    })

    test('the host stores it and renders: the item is where it was put before the answer, and where the server has it after', async ({ page }) => {
        await mode(page, 'livewire')
        await dragHex(page)

        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')

        // the host has it as it was told, in the viewer's wall clock, and its instant is the app's zone (Kuala Lumpur, +8)
        await expect.poll(() => rescheduled(page)).toEqual([
            { id: 10, start: '2026-10-22T14:00', end: '2026-10-22T15:00', startUtc: '2026-10-22T06:00:00Z' },
        ])

        await expect.poll(async () => (await engine(page)).overrides).toEqual([])
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')
    })

    test('the host stores it with no render: the item stays where it was put, and the next payload, which has it there, leaves it there', async ({ page }) => {
        await mode(page, 'quiet')
        await dragHex(page)

        await expect.poll(() => rescheduled(page)).toHaveLength(1)
        await page.waitForTimeout(200)
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')
        expect((await engine(page)).overrides).toEqual(['10'])

        await page.locator('[data-toggle-extra]').click()
        await payloadArrived(page, 'Added later')

        await expect.poll(async () => (await engine(page)).overrides).toEqual([])
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-22')
    })

    test('a resize is held the same way: pending, it keeps its length; rejected, it is back', async ({ page }) => {
        await mode(page, 'reject')
        await drag(page, await middle(handleOf(page, 'bar', 'Launch week')), await inDay(page, '2026-10-22'))

        // from the 5th to the 22nd, over three weeks
        await expect(chipOf(page, 'bar', 'Launch week')).toHaveCount(3)

        await page.evaluate(() => window.__settle.reject(new Error('no')))
        await expect(chipOf(page, 'bar', 'Launch week')).toHaveCount(1)
    })

    test('an item is moved again from where it was put, and the second move is the one that is held', async ({ page }) => {
        await dragHex(page)
        await drag(page, await middle(chipOf(page, 'dot', 'Hex colour')), await inDay(page, '2026-10-23'))

        expect(await moves(page)).toEqual([
            { id: 10, start: '2026-10-22T14:00', end: '2026-10-22T15:00', allDay: false },
            { id: 10, start: '2026-10-23T14:00', end: '2026-10-23T15:00', allDay: false },
        ])
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-23')
    })
})

test.describe('Escape and the other ends of a drag that was not let go', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    async function holdStandup (page) {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))
        const to = await inDay(page, '2026-10-14')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)

        return { from, to }
    }

    test('Escape gives the drag up: the ghost goes, the item is as it was, nobody is told, and the release that comes after is not the item\'s click either', async ({ page }) => {
        const { from } = await holdStandup(page)

        await page.keyboard.press('Escape')

        await expect(ghosts(page)).toHaveCount(0)
        await expect(chipOf(page, 'dot', 'Team standup')).not.toHaveAttribute('data-lifted', '')
        expect((await engine(page)).gesture).toBe(false)

        // still down: moving it and letting go over the item, a while after, does nothing
        await page.mouse.move(from.x, from.y, { steps: 4 })
        await page.waitForTimeout(250)
        await page.mouse.up()

        expect(await moves(page)).toEqual([])
        expect(await page.evaluate(() => window.__clicks)).toEqual([])
        expect(await startsOn(page, chipOf(page, 'dot', 'Team standup'))).toBe('2026-10-09')
        expect((await engine(page)).overrides).toEqual([])
    })

    test('Escape gives up a pull of the end of an item', async ({ page }) => {
        const from = await middle(handleOf(page, 'bar', 'Launch week'))

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 250, from.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)

        await page.keyboard.press('Escape')
        await expect(ghosts(page)).toHaveCount(0)

        await page.mouse.up()
        expect(await resizes(page)).toEqual([])
    })

    test('it is the drag\'s, and does not reach what is around the calendar', async ({ page }) => {
        await page.evaluate(() => {
            window.__escapes = 0
            document.addEventListener('keydown', (event) => event.key === 'Escape' && window.__escapes++)
        })

        await holdStandup(page)
        await page.keyboard.press('Escape')
        await page.mouse.up()

        expect(await page.evaluate(() => window.__escapes)).toBe(0)
    })

    test('the window losing its focus gives it up, and so does the pointer moving with no button down', async ({ page }) => {
        const { to } = await holdStandup(page)

        await page.evaluate(() => window.dispatchEvent(new Event('blur')))
        await expect(ghosts(page)).toHaveCount(0)
        expect((await engine(page)).gesture).toBe(false)

        await page.mouse.up()
        expect(await moves(page)).toEqual([])

        // the same drag, with a release that nobody heard
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        await page.evaluate(([x, y]) => {
            document.elementFromPoint(x, y).dispatchEvent(new PointerEvent('pointerdown', { pointerId: 1, pointerType: 'mouse', isPrimary: true, button: 0, buttons: 1, bubbles: true, cancelable: true, composed: true, clientX: x, clientY: y }))
        }, [from.x, from.y])
        expect((await engine(page)).gesture).toBe(true)

        await page.mouse.move(to.x - 30, to.y, { steps: 3 })
        await page.mouse.move(to.x, to.y, { steps: 3 })

        expect((await engine(page)).gesture).toBe(false)
        await expect(ghosts(page)).toHaveCount(0)
        expect(await moves(page)).toEqual([])
    })

    test('a context menu opening gives it up', async ({ page }) => {
        const { to } = await holdStandup(page)

        await page.evaluate(([x, y]) => document.elementFromPoint(x, y).dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: x, clientY: y })), [to.x, to.y])

        expect((await engine(page)).gesture).toBe(false)
        await expect(ghosts(page)).toHaveCount(0)
        await page.mouse.up()
        expect(await moves(page)).toEqual([])
    })

    test('the page being hidden gives it up', async ({ page }) => {
        await holdStandup(page)

        await page.evaluate(() => {
            Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' })
            document.dispatchEvent(new Event('visibilitychange'))
        })

        expect((await engine(page)).gesture).toBe(false)
        await expect(ghosts(page)).toHaveCount(0)
    })

    test('a press with a key held is not the start of one', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        for (const modifier of ['ctrlKey', 'metaKey', 'altKey', 'shiftKey']) {
            await page.evaluate(([x, y, key]) => {
                document.elementFromPoint(x, y).dispatchEvent(new PointerEvent('pointerdown', { pointerId: 1, pointerType: 'mouse', isPrimary: true, button: 0, buttons: 1, bubbles: true, cancelable: true, composed: true, clientX: x, clientY: y, [key]: true }))
            }, [from.x, from.y, modifier])

            expect((await engine(page)).gesture).toBe(false)
        }
    })

    test('and neither is a press of the right button', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        await page.mouse.move(from.x, from.y)
        await page.mouse.down({ button: 'right' })
        expect((await engine(page)).gesture).toBe(false)
        await page.mouse.up({ button: 'right' })
    })
})

test.describe('while an item is being moved and a payload comes', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('the ghost is not dropped, the payload waits, and what the host is told is where the pointer ended; the payload is taken when it is let go', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Hex colour'))
        const middlePoint = await inDay(page, '2026-10-21')
        const to = await inDay(page, '2026-10-23')
        const before = (await engine(page)).items

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(middlePoint.x, middlePoint.y, { steps: 6 })

        // the host re-renders with another item while the pointer is down: it has come to the page, and the calendar has not taken it
        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await payloadArrived(page, 'Added later')
        await page.waitForTimeout(250)

        await expect(chipOf(page, 'bar', 'Added later')).toHaveCount(0)
        expect((await engine(page)).items).toBe(before)
        await expect(ghosts(page)).toHaveCount(1)
        expect((await engine(page)).gesture).toBe(true)

        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)
        await page.mouse.up()

        expect(await moves(page)).toEqual([{ id: 10, start: '2026-10-23T14:00', end: '2026-10-23T15:00', allDay: false }])

        // let go: the payload is applied, and the item is where it was put
        await expect(chipOf(page, 'bar', 'Added later')).toBeVisible()
        expect((await engine(page)).items).toBe(before + 1)
        expect(await startsOn(page, chipOf(page, 'dot', 'Hex colour'))).toBe('2026-10-23')
    })

    test('a payload that takes the item away cancels the drag: no ghost, nobody is told, and the payload is taken', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Hex colour'))
        const to = await inDay(page, '2026-10-21')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)

        await page.evaluate(() => window.Livewire.first().hide(10))

        await expect(chipOf(page, 'dot', 'Hex colour')).toHaveCount(0)
        await expect(ghosts(page)).toHaveCount(0)
        expect((await engine(page)).gesture).toBe(false)

        await page.mouse.move(to.x + 10, to.y, { steps: 3 })
        await page.mouse.up()

        expect(await moves(page)).toEqual([])
        expect(await page.evaluate(() => window.__errors)).toEqual([])
    })

    test('in the time grid: the same', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12')

        const block = chipOf(page, 'block', 'Design review')
        const from = await middle(block)
        const to = await at(page, '2026-10-15', 13)
        const before = (await engine(page)).items

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(shapes(page)).toHaveCount(1)

        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await payloadArrived(page, 'Added later')
        await page.waitForTimeout(250)

        expect((await engine(page)).items).toBe(before)
        await expect(shapes(page)).toHaveCount(1)

        await page.mouse.up()
        expect((await moves(page)).at(-1)).toMatchObject({ id: 6, allDay: false })
    })
})

test.describe('the time grid', () => {
    // tall enough to show the hours the tests work in clear of the edges, where the grid scrolls under the pointer (see the last describe)
    test.use({ viewport: { width: 1280, height: 1400 } })

    test.beforeEach(async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12')
    })

    test('a block moves by the slots the pointer moved, and keeps its length: held in the middle, put down by the time it is at', async ({ page }) => {
        // Design review, 10:00 to 11:00 on the 14th: held in its 10:00 slot, put down in the 14:00 slot of the 15th, and it starts 4 hours later, a day on
        const block = chipOf(page, 'block', 'Design review')

        await drag(page, await near(block), await at(page, '2026-10-15', 14.1))

        expect(await moves(page)).toEqual([{ id: 6, start: '2026-10-15T14:00', end: '2026-10-15T15:00', allDay: false }])

        // and it is there: in the column of the 15th, from 14:00
        const moved = await chipOf(page, 'block', 'Design review').boundingBox()
        const col = await column(page, '2026-10-15').boundingBox()
        const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

        expect(Math.abs(moved.x - col.x)).toBeLessThan(4)
        expect(Math.abs(moved.y - (col.y + 14 * HOUR * rem))).toBeLessThan(3)
        expect(Math.abs(moved.height - HOUR * rem)).toBeLessThan(3)
    })

    test('it snaps to the slot: a point a little before the next slot is still in this one', async ({ page }) => {
        // 13:55 is in the 13:30 slot, so the block that was held in its 10:00 slot is moved by 3 hours and a half
        await drag(page, await near(chipOf(page, 'block', 'Design review')), await at(page, '2026-10-14', 13 + 55 / 60))

        expect(await moves(page)).toEqual([{ id: 6, start: '2026-10-14T13:30', end: '2026-10-14T14:30', allDay: false }])
    })

    test('the ghost is drawn at the slot while the pointer is down, at the length of the item, and the original is lifted', async ({ page }) => {
        const block = chipOf(page, 'block', 'Design review')
        const from = await near(block)
        const to = await at(page, '2026-10-14', 13.2)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 8 })

        await expect(shapes(page)).toHaveCount(1)
        await expect(shapes(page)).toContainText('Design review')
        await expect(shapes(page)).toContainText('1:00 PM – 2:00 PM')
        await expect(block).toHaveAttribute('data-lifted', '')
        expect(await moves(page)).toEqual([])

        await page.mouse.up()
        await expect(shapes(page)).toHaveCount(0)
    })

    test('a block with no end moves with no end', async ({ page }) => {
        // Call the bank: 15:00, no end
        await drag(page, await middle(chipOf(page, 'block', 'Call the bank')), await at(page, '2026-10-14', 16.1))

        expect(await moves(page)).toEqual([{ id: 8, start: '2026-10-14T16:00', end: null, allDay: false }])
    })

    test('an item that goes on past midnight moves over the date, both ends', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-19')

        await scrollGrid(page, 22 * HOUR * 16 - 300)

        // Night shift: 22 October 22:00 to 23 October 02:00. Held in the 22:00 slot of the 22nd, put down in the 22:30 slot
        const from = await near(chipOf(page, 'block', 'Night shift').first())

        await drag(page, from, { x: from.x, y: from.y + 28 })

        expect(await moves(page)).toEqual([{ id: 12, start: '2026-10-22T22:30', end: '2026-10-23T02:30', allDay: false }])
    })

    test('the bottom edge of a block is pulled to another slot: the end changes, the start stays', async ({ page }) => {
        const handle = handleOf(page, 'block', 'Design review')
        const box = await handle.boundingBox()

        // a few px tall, on the bottom edge
        expect(box.height).toBeLessThan(8)

        await drag(page, { x: box.x + box.width / 2, y: box.y + box.height / 2 }, await at(page, '2026-10-14', 12.1))

        // the pointer is in the 12:00 slot, so the block ends at the end of it
        expect(await resizes(page)).toEqual([{ id: 6, start: '2026-10-14T10:00', end: '2026-10-14T12:30' }])
        expect(await moves(page)).toEqual([])

        const resized = await chipOf(page, 'block', 'Design review').boundingBox()
        const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

        expect(Math.abs(resized.height - 2.5 * HOUR * rem)).toBeLessThan(3)
    })

    test('a block is at least one slot long', async ({ page }) => {
        const box = await handleOf(page, 'block', 'Design review').boundingBox()

        await drag(page, { x: box.x + box.width / 2, y: box.y + box.height / 2 }, await at(page, '2026-10-14', 9))

        expect(await resizes(page)).toEqual([{ id: 6, start: '2026-10-14T10:00', end: '2026-10-14T10:30' }])
    })

    test('a block with no end has one when it is pulled, from where it was drawn to end', async ({ page }) => {
        const box = await handleOf(page, 'block', 'Call the bank').boundingBox()

        await drag(page, { x: box.x + box.width / 2, y: box.y + box.height / 2 }, await at(page, '2026-10-14', 16.1))

        // drawn as half an hour, 15:00 to 15:30, and pulled an hour on
        expect(await resizes(page)).toEqual([{ id: 8, start: '2026-10-14T15:00', end: '2026-10-14T16:30' }])
    })

    test('a pull that ends where the block ended is not a resize, and a block with no end is not given one by it', async ({ page }) => {
        const box = await handleOf(page, 'block', 'Call the bank').boundingBox()
        const from = { x: box.x + box.width / 2, y: box.y + box.height / 2 }

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x, from.y - 9, { steps: 4 })
        await expect(shapes(page)).toHaveCount(1)
        await page.mouse.up()

        expect(await resizes(page)).toEqual([])
        expect((await engine(page)).overrides).toEqual([])
    })

    test('the ghost of a move takes a lane beside what it is over, the way it will be drawn when it is dropped', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-26&busy=1')

        // Solo (13:00 to 14:00) over Planning and Review (09:00 to 10:30), which are two lanes
        const solo = chipOf(page, 'block', 'Solo')
        const from = await near(solo)
        const to = { x: from.x, y: from.y - 4 * HOUR * 16 }

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 8 })

        await expect(shapes(page)).toHaveCount(1)

        const col = await column(page, '2026-10-27').boundingBox()
        const ghost = await shapes(page).boundingBox()

        // not the whole column
        expect(ghost.width).toBeLessThan(col.width * 0.6)

        await page.mouse.up()

        // dropped: the block is in the lane the ghost was in
        const dropped = await chipOf(page, 'block', 'Solo').boundingBox()

        expect(Math.abs(dropped.x - ghost.x)).toBeLessThan(4)
        expect(Math.abs(dropped.width - ghost.width)).toBeLessThan(4)
    })

    test('a resize keeps the lane of the item', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-26&busy=1')

        const review = chipOf(page, 'block', 'Review')
        const lane = await review.boundingBox()
        const handle = await handleOf(page, 'block', 'Review').boundingBox()
        const from = { x: handle.x + handle.width / 2, y: handle.y + handle.height / 2 }

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x, from.y + 60, { steps: 6 })

        const ghost = await shapes(page).boundingBox()

        expect(Math.abs(ghost.x - lane.x)).toBeLessThan(4)
        expect(Math.abs(ghost.width - lane.width)).toBeLessThan(4)

        await page.mouse.up()
    })
})

test.describe('the all-day row', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'date=2026-10-09&view=week', '/atom/e2e/calendar-grid')
    })

    test('an all-day item moves by days, and the ghost is a bar in the row', async ({ page }) => {
        const holiday = chipOf(page, 'bar', 'Public holiday', 'grid')
        const from = await middle(holiday)
        const to = await inAllDay(page, '2026-10-11', holiday, 'grid')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 8 })

        await expect(allDayGhosts(page, 'grid')).toHaveCount(1)
        await expect(allDayGhosts(page, 'grid')).toContainText('Public holiday')
        await expect(holiday).toHaveAttribute('data-lifted', '')

        await page.mouse.up()

        expect(await page.evaluate(() => window.__moves)).toEqual([{ id: 'holiday', start: '2026-10-11', end: '2026-10-11', allDay: true }])
        await expect(allDayGhosts(page, 'grid')).toHaveCount(0)

        // it is on the Sunday now
        const moved = await chipOf(page, 'bar', 'Public holiday', 'grid').boundingBox()
        const sunday = await allDayCell(page, '2026-10-11', 'grid').boundingBox()

        expect(Math.abs(moved.x - sunday.x)).toBeLessThan(8)
    })

    test('a bar of a week is moved whole, and keeps its width', async ({ page }) => {
        const launch = chipOf(page, 'bar', 'Launch week', 'grid')
        const box = await launch.boundingBox()
        const from = await inAllDay(page, '2026-10-07', launch, 'grid')

        await drag(page, from, await inAllDay(page, '2026-10-08', launch, 'grid'))

        expect(await page.evaluate(() => window.__moves)).toEqual([{ id: 'launch', start: '2026-10-06', end: '2026-10-10', allDay: true }])

        const moved = await chipOf(page, 'bar', 'Launch week', 'grid').boundingBox()

        expect(Math.abs(moved.width - box.width)).toBeLessThan(3)
    })

    test('the end of a bar is pulled to another day', async ({ page }) => {
        const launch = chipOf(page, 'bar', 'Launch week', 'grid')
        const handle = launch.locator('[data-atom-calendar-handle]')

        await drag(page, await middle(handle), await inAllDay(page, '2026-10-11', launch, 'grid'))

        expect(await page.evaluate(() => window.__resizes)).toEqual([{ id: 'launch', start: '2026-10-05', end: '2026-10-11' }])
        expect(await page.evaluate(() => window.__moves)).toEqual([])
    })
})

test.describe('a viewer\'s own slots and timezone', () => {
    test('a slot of a quarter of an hour snaps to a quarter', async ({ page }) => {
        await open(page, 'date=2026-10-09&view=week&slot=15', '/atom/e2e/calendar-grid')

        // Team standup: 09:30 to 10:15. Held a little under its top (the 09:30 slot) and put down 22 minutes under it, which is in the 09:45 slot
        const block = chipOf(page, 'block', 'Team standup', 'grid')
        const box = await block.boundingBox()
        const hour = await hourPx(page, 'grid')
        const from = { x: box.x + box.width / 2, y: box.y + 3 }

        await drag(page, from, { x: from.x, y: box.y + hour * 22 / 60 })

        expect(await page.evaluate(() => window.__moves)).toEqual([{ id: 'standup', start: '2026-10-09T09:45', end: '2026-10-09T10:30', allDay: false }])
    })

    for (const timezoneId of ['America/Los_Angeles', 'Pacific/Kiritimati']) {
        test.describe(`in a browser in ${timezoneId}`, () => {
            test.use({ timezoneId })

            test('an item moved says the viewer\'s wall clock, whatever the browser\'s own is', async ({ page }) => {
                await open(page, 'date=2026-10-09&view=week', '/atom/e2e/calendar-grid')

                // Design review: 10:00 to 11:30 on the 9th. Held at its 10:00 slot (the top), put down an hour and a half later
                const block = chipOf(page, 'block', 'Design review', 'grid')
                const box = await block.boundingBox()
                const hour = await hourPx(page, 'grid')
                const from = { x: box.x + box.width - 8, y: box.y + 6 }

                await drag(page, from, { x: from.x, y: from.y + 1.5 * hour })

                expect(await page.evaluate(() => window.__moves)).toEqual([{ id: 'design', start: '2026-10-09T11:30', end: '2026-10-09T13:00', allDay: false }])
            })
        })
    }
})

test.describe('the time grid scrolls under a pointer at its edge', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12&selectable=1')
    })

    test('under a block that is being moved: down at the bottom edge, and the item goes to the hours it scrolls to', async ({ page }) => {
        const grid = await probe(page).locator('[data-atom-calendar-grid]').boundingBox()
        const from = await middle(chipOf(page, 'block', 'Design review'))
        const before = await scrollTop(page)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x, grid.y + grid.height - 10, { steps: 6 })

        // still, at the edge: it goes on
        await expect.poll(() => scrollTop(page)).toBeGreaterThan(before + 120)
        await page.waitForTimeout(400)
        await page.mouse.up()

        const [move] = await moves(page)

        expect(move.id).toBe(6)
        // the hours that were on screen at the start ended at about half past two
        expect(move.start > '2026-10-14T17:00').toBe(true)
        expect(move.end > move.start).toBe(true)
    })

    test('and up at the top edge, under the header that stays on top', async ({ page }) => {
        await scrollGrid(page, 500)

        const grid = await probe(page).locator('[data-atom-calendar-grid]').boundingBox()
        const header = await probe(page).locator('[data-atom-calendar-grid-top]').boundingBox()
        const block = await chipOf(page, 'block', 'School pickup').boundingBox()
        const from = { x: block.x + block.width / 2, y: block.y + block.height / 2 }
        const before = await scrollTop(page)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x, header.y + header.height + 8, { steps: 6 })

        await expect.poll(() => scrollTop(page)).toBeLessThan(before - 120)
        await page.waitForTimeout(400)
        await page.mouse.up()
        expect(grid.height).toBeGreaterThan(0)
        expect((await moves(page))[0].start < '2026-10-14T12:00').toBe(true)
    })

    test('not in the middle of it', async ({ page }) => {
        const from = await middle(chipOf(page, 'block', 'Design review'))
        const before = await scrollTop(page)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 30, from.y + 20, { steps: 6 })
        await page.waitForTimeout(500)

        expect(await scrollTop(page)).toBe(before)
        await page.mouse.up()
    })

    test('under the pull of an end, as well', async ({ page }) => {
        const grid = await probe(page).locator('[data-atom-calendar-grid]').boundingBox()
        const handle = await middle(handleOf(page, 'block', 'Design review'))
        const before = await scrollTop(page)

        await page.mouse.move(handle.x, handle.y)
        await page.mouse.down()
        await page.mouse.move(handle.x, grid.y + grid.height - 10, { steps: 6 })
        await expect.poll(() => scrollTop(page)).toBeGreaterThan(before + 120)
        await page.waitForTimeout(400)
        await page.mouse.up()

        const [resize] = await resizes(page)

        expect(resize.end > '2026-10-14T17:00').toBe(true)
    })

    test('and under a selection, which it was not for in the first place', async ({ page }) => {
        const grid = await probe(page).locator('[data-atom-calendar-grid]').boundingBox()
        const from = await at(page, '2026-10-13', 9)
        const before = await scrollTop(page)

        await page.mouse.move(from.x, from.y + 4)
        await page.mouse.down()
        await page.mouse.move(from.x, grid.y + grid.height - 10, { steps: 6 })
        await expect.poll(() => scrollTop(page)).toBeGreaterThan(before + 120)
        await page.waitForTimeout(400)
        await page.mouse.up()

        const [select] = await page.evaluate(() => window.__selects ?? [])

        expect(select?.end > '2026-10-13T17:00').toBe(true)
    })

    test('a grid that does not scroll has nothing to scroll', async ({ page }) => {
        await page.evaluate(() => document.documentElement.style.setProperty('--atom-calendar-grid-height', '4000px'))

        const from = await middle(chipOf(page, 'block', 'Design review'))

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x, from.y + 300, { steps: 6 })
        await page.waitForTimeout(300)

        expect((await engine(page)).gesture).toBe(true)
        await page.mouse.up()
    })
})

test.describe('a touch', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('a tap is the item\'s click', async ({ page }) => {
        const point = await middle(chipOf(page, 'dot', 'Team standup'))

        await pointer(page, 'pointerdown', point)
        await pointer(page, 'pointerup', point)
        await page.evaluate(([x, y]) => document.elementFromPoint(x, y).click(), [point.x, point.y])

        expect(await moves(page)).toEqual([])
        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
    })

    test('a finger that moves before the long press is scrolling: nothing is moved and the page may scroll', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))
        const to = { x: from.x + 3, y: from.y - 40 }

        await pointer(page, 'pointerdown', from)
        await pointer(page, 'pointermove', to)

        expect(await touchMoveAllowed(page, to)).toBe(true)
        expect((await engine(page)).gesture).toBe(false)

        await page.waitForTimeout(700)
        await expect(ghosts(page)).toHaveCount(0)
        await pointer(page, 'pointerup', to)
        expect(await moves(page)).toEqual([])
    })

    test('a long press picks the item up, and from then on the finger is the item\'s: the page may not scroll under it, and touch-action is none on that item and no other', async ({ page }) => {
        const standup = chipOf(page, 'dot', 'Team standup')
        const from = await middle(standup)
        const to = await inDay(page, '2026-10-14')

        await pointer(page, 'pointerdown', from)

        // not yet: before the press is long, the touch is a touch
        await expect(ghosts(page)).toHaveCount(0)
        expect(await touchMoveAllowed(page, from)).toBe(true)
        expect(await standup.evaluate((el) => el.style.touchAction)).toBe('')

        await expect(ghosts(page)).toHaveCount(1, { timeout: 1500 })
        await expect(standup).toHaveAttribute('data-lifted', '')
        expect(await touchMoveAllowed(page, from)).toBe(false)
        expect(await standup.evaluate((el) => el.style.touchAction)).toBe('none')
        expect(await chipOf(page, 'bar', 'Launch week').evaluate((el) => el.style.touchAction)).toBe('')

        await pointer(page, 'pointermove', to)
        await pointer(page, 'pointerup', to)

        expect(await moves(page)).toEqual([{ id: 1, start: '2026-10-14T09:30', end: '2026-10-14T10:00', allDay: false }])
        expect((await engine(page)).gesture).toBe(false)
    })

    test('the handle of a bar pulled by a finger resizes it', async ({ page }) => {
        const from = await middle(handleOf(page, 'bar', 'Launch week'))
        const to = await inDay(page, '2026-10-12')

        await pointer(page, 'pointerdown', from)
        await expect(ghosts(page)).toHaveCount(1, { timeout: 1500 })
        await pointer(page, 'pointermove', to)
        await pointer(page, 'pointerup', to)

        expect(await resizes(page)).toEqual([{ id: 2, start: '2026-10-05', end: '2026-10-12' }])
    })

    test('a long press that is let go where it was moves nothing, and is not the item\'s click', async ({ page }) => {
        const standup = chipOf(page, 'dot', 'Team standup')
        const from = await middle(standup)

        await pointer(page, 'pointerdown', from)
        await expect(ghosts(page)).toHaveCount(1, { timeout: 1500 })
        expect(await standup.evaluate((el) => el.style.touchAction)).toBe('none')
        await pointer(page, 'pointerup', from)

        expect(await moves(page)).toEqual([])
        await expect(ghosts(page)).toHaveCount(0)
        // given back
        expect(await standup.evaluate((el) => el.style.touchAction)).toBe('')

        // the click a browser sends after the release of a press
        await page.evaluate(([x, y]) => document.elementFromPoint(x, y).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, clientX: x, clientY: y })), [from.x, from.y])
        expect(await page.evaluate(() => window.__clicks)).toEqual([])
    })

    test('the browser taking the touch gives it up', async ({ page }) => {
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        await pointer(page, 'pointerdown', from)
        await expect(ghosts(page)).toHaveCount(1, { timeout: 1500 })
        await pointer(page, 'pointercancel', from)

        await expect(ghosts(page)).toHaveCount(0)
        expect((await engine(page)).gesture).toBe(false)
        await pointer(page, 'pointerup', from)
        expect(await moves(page)).toEqual([])
    })

    test('a block of the time grid is picked up by a long press too', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12')

        const from = await near(chipOf(page, 'block', 'Design review'))
        const to = await at(page, '2026-10-14', 14.1)

        await pointer(page, 'pointerdown', from)
        await expect(shapes(page)).toHaveCount(1, { timeout: 1500 })
        await pointer(page, 'pointermove', to)
        await pointer(page, 'pointerup', to)

        expect(await moves(page)).toEqual([{ id: 6, start: '2026-10-14T14:00', end: '2026-10-14T15:00', allDay: false }])
    })
})

test.describe('two calendars on a page, and one that goes', () => {
    test('a drag in one is not the other\'s: the other has no gesture, no ghost and no moved item', async ({ page }) => {
        await open(page, '', '/atom/e2e/calendar-twin')

        const probes = page.locator('[data-probe="calendar"]')
        const first = probes.nth(0)
        const second = probes.nth(1)
        const standup = (root) => root.locator('[data-atom-calendar-chip="dot"]', { hasText: 'Team standup' })
        const before = await standup(second).boundingBox()

        await page.evaluate(() => {
            window.__moveTargets = []
            document.addEventListener('calendar-item-move', (event) => window.__moveTargets.push([...document.querySelectorAll('[data-atom-calendar]')].indexOf(event.target)))
        })

        const from = await middle(standup(first))
        const day = await first.locator('[data-atom-calendar-day][data-date="2026-10-14"]').boundingBox()
        const to = { x: day.x + day.width / 2, y: day.y + day.height * 0.9 }

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })

        expect((await engine(page, 0)).gesture).toBe(true)
        expect((await engine(page, 1)).gesture).toBe(false)
        await expect(first.locator('[data-atom-calendar-ghost]')).toHaveCount(1)
        await expect(second.locator('[data-atom-calendar-ghost]')).toHaveCount(0)

        await page.mouse.up()

        expect(await page.evaluate(() => window.__moveTargets)).toEqual([0])
        expect((await engine(page, 0)).overrides).toEqual(['1'])
        expect((await engine(page, 1)).overrides).toEqual([])
        expect(await standup(second).boundingBox()).toEqual(before)
    })

    test('a calendar that is taken out of the page mid-drag takes its gesture with it: nothing follows the pointer, nothing is told, nothing throws', async ({ page }) => {
        await open(page)

        const from = await middle(chipOf(page, 'dot', 'Team standup'))
        const to = await inDay(page, '2026-10-14')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)

        await page.evaluate(() => {
            window.__engine = window.Alpine.$data(document.querySelector('[data-atom-calendar]'))
            window.__escapes = 0
            document.addEventListener('keydown', (event) => event.key === 'Escape' && window.__escapes++)
            document.querySelector('[data-atom-calendar]').remove()
        })
        await page.waitForTimeout(100)

        // the gesture went with it: it holds nothing, and its Escape is the page's again
        expect(await page.evaluate(() => window.__engine.gesture)).toBeNull()

        await page.mouse.move(to.x + 50, to.y, { steps: 3 })
        await page.mouse.up()
        await page.keyboard.press('Escape')

        expect(await page.evaluate(() => window.__escapes)).toBe(1)
        expect(await moves(page)).toEqual([])
        expect(await page.evaluate(() => window.__errors)).toEqual([])
    })

    test('a page that is navigated away from takes the gesture down', async ({ page }) => {
        await open(page)

        const from = await middle(chipOf(page, 'dot', 'Team standup'))
        const to = await inDay(page, '2026-10-14')

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(to.x, to.y, { steps: 6 })
        await expect(ghosts(page)).toHaveCount(1)

        await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigating')))

        expect((await engine(page)).gesture).toBe(false)
        await expect(ghosts(page)).toHaveCount(0)
        await page.mouse.up()
        expect(await moves(page)).toEqual([])
    })
})

test.describe('the pointer on the body while a gesture is on', () => {
    test('the cursor says what is being done, and goes with the gesture', async ({ page }) => {
        await open(page)

        const body = probe(page).locator('[data-atom-calendar-body]')
        const from = await middle(chipOf(page, 'dot', 'Team standup'))

        await expect(body).not.toHaveAttribute('data-atom-calendar-gesture', /.*/)

        await page.mouse.move(from.x, from.y)
        await page.mouse.down()
        await page.mouse.move(from.x + 120, from.y + 90, { steps: 6 })

        await expect(body).toHaveAttribute('data-atom-calendar-gesture', 'move')
        expect(await body.locator('[data-atom-calendar-chip]').first().evaluate((el) => getComputedStyle(el).cursor)).toBe('grabbing')

        await page.mouse.up()
        await expect(body).not.toHaveAttribute('data-atom-calendar-gesture', /.*/)

        // an item that can be picked up shows it, and its end shows what it is for
        expect(await chipOf(page, 'dot', 'Team standup').evaluate((el) => getComputedStyle(el).cursor)).toBe('grab')
        expect(await handleOf(page, 'bar', 'Launch week').evaluate((el) => getComputedStyle(el).cursor)).toBe('ew-resize')
    })
})
