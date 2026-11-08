import { test, expect, devices } from '@playwright/test'

// A finger on a phone (an iPhone 14: a touch screen, so `(pointer: coarse)`, 390 px wide). A long press anywhere on an item moves it and picks it up:
// the item shows a grip on each end it can be pulled by, and keeps them once it is let go, until a press elsewhere or Escape puts it down. A grip is
// what a finger resizes by (there is no handle for a finger), and a pull stays on its day in the time grid. In the month a phone shows dots, and an
// item is picked up from the day list and put on a day of the grid.
//
// The fixture is the Livewire one of calendar-move.spec.js (/atom/e2e/calendar, October 2026 in Kuala Lumpur), whose host script records what it is
// told (window.__moves, window.__resizes). The finger is pointer events sent by hand, as the other touch specs send them, to the element it pressed
// for as long as that is on the page (a touch's pointer is captured by what it pressed), with the touchmove a browser sends beside each move.
// It runs in Chromium and in WebKit (the `webkit-move` project), the engine of a real iPhone. Firefox has no mobile emulation.

const { defaultBrowserType, ...iPhone } = devices['iPhone 14']

test.use({ ...iPhone })

const HOUR = 3.5

const probe = (page) => page.locator('[data-probe="calendar"]')
const column = (page, date) => probe(page).locator(`[data-atom-calendar-column][data-date="${date}"]`)
const block = (page, text) => probe(page).locator('[data-atom-calendar-block]', { hasText: text })
const chipOf = (page, kind, text) => probe(page).locator(`[data-atom-calendar-chip="${kind}"]:visible`, { hasText: text })
const grips = (page, text) => block(page, text).locator('[data-atom-calendar-grip]:visible')
const grip = (page, text, edge) => block(page, text).locator(`[data-atom-calendar-grip="${edge}"]`)
const ghosts = (page) => probe(page).locator('[data-atom-calendar-column] [data-atom-calendar-preview]')
const grid = (page) => probe(page).locator('[data-atom-calendar-grid]')

async function open (page, query = '') {
    await page.goto('/atom/e2e/calendar' + (query ? '?' + query : ''))
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)

    await page.evaluate(() => {
        window.__clicks = []
        document.addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail.id))
    })
}

const moves = (page) => page.evaluate(() => window.__moves)
const resizes = (page) => page.evaluate(() => window.__resizes)
const picked = (page) => page.evaluate(() => window.Alpine.$data(document.querySelector('[data-atom-calendar]')).picked)

// A finger: a press, moves and a release, each a pointer event (and a move a touchmove too) on what the finger pressed, while it is on the page.
// `move` answers whether the page was let scroll under it (false when the calendar prevented the touchmove).
const finger = (page) => {
    const send = (type, { x, y }) => page.evaluate(([kind, px, py]) => {
        let target = window.__finger?.isConnected ? window.__finger : (document.elementFromPoint(px, py) ?? document.body)

        if (kind === 'pointerdown') {
            target = document.elementFromPoint(px, py) ?? document.body
            window.__finger = target
        }

        target.dispatchEvent(new PointerEvent(kind, { pointerId: 11, pointerType: 'touch', isPrimary: true, button: kind === 'pointermove' ? -1 : 0, buttons: kind === 'pointerup' ? 0 : 1, bubbles: true, cancelable: true, composed: true, clientX: px, clientY: py }))

        if (kind !== 'pointermove') return null

        const touch = new Event('touchmove', { bubbles: true, cancelable: true })

        target.dispatchEvent(touch)

        return !touch.defaultPrevented
    }, [type, x, y])

    return {
        down: (point) => send('pointerdown', point),
        up: (point) => send('pointerup', point),
        // a few steps on the way, as a finger sends them
        async move (from, to, steps = 6) {
            let allowed = null

            for (let i = 1; i <= steps; i++) allowed = await send('pointermove', { x: from.x + (to.x - from.x) * i / steps, y: from.y + (to.y - from.y) * i / steps })

            return allowed
        },
        // a tap: a press and a release where it pressed, and the click a browser sends after them
        async tap (point) {
            await send('pointerdown', point)
            await send('pointerup', point)
            await page.evaluate(([x, y]) => document.elementFromPoint(x, y)?.click(), [point.x, point.y])
        },
    }
}

// a point in a box: `share` of the way across it and down it
async function inBox (locator, across = 0.5, down = 0.5) {
    const box = await locator.first().boundingBox()

    return { x: box.x + box.width * across, y: box.y + box.height * down }
}

const hourPx = async (page) => (await column(page, '2026-10-05').boundingBox()).height / 24

// The grid scrolled so that a block is in the middle of its visible part, second from the left of the days shown (a phone shows three and a half)
const centre = (page, title) => grid(page).evaluate((el, text) => {
    const target = [...el.querySelectorAll('[data-atom-calendar-block]')].find((candidate) => candidate.textContent.includes(text))
    const top = el.querySelector('[data-atom-calendar-grid-top]').offsetHeight
    const gutter = el.querySelector('[data-atom-calendar-gutter]').offsetWidth
    const box = target.getBoundingClientRect()
    const view = el.getBoundingClientRect()
    const day = target.closest('[data-atom-calendar-column]').offsetWidth

    el.scrollTop += (box.top + box.height / 2) - (view.top + top + (view.height - top) / 2)
    el.scrollLeft += box.left - (view.left + gutter + day)
}, title).then(() => page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))))

// The week of the 5th on a phone, with Team standup (the 9th, 09:30 to 10:00: half an hour, the shortest a block is drawn) in the middle, the day before it beside it
async function weekOfStandup (page) {
    await open(page, 'view=week&date=2026-10-05')
    await centre(page, 'Team standup')

    await expect(chipOf(page, 'block', 'Team standup')).toBeVisible()
    await expect(page.evaluate(() => matchMedia('(pointer: coarse)').matches)).resolves.toBe(true)
}

// the same place a day earlier: the column of the 8th, at the height of the point
async function dayBefore (page, point) {
    const box = await column(page, '2026-10-08').boundingBox()

    return { x: box.x + box.width / 2, y: point.y }
}

// a long press: held until the item is picked up, its ghost up
async function longPress (page, hand, point) {
    await hand.down(point)
    await expect(ghosts(page)).toHaveCount(1, { timeout: 1500 })
}

// pick Team standup up and let it go where it is: it is picked up, and nothing is moved
async function pickStandup (page, hand) {
    const point = await inBox(chipOf(page, 'block', 'Team standup'))

    await longPress(page, hand, point)
    await hand.up(point)
    await expect(grips(page, 'Team standup')).toHaveCount(2)
}

test.describe('a long press on an item in the time grid', () => {
    test.beforeEach(async ({ page }) => {
        await weekOfStandup(page)
    })

    // the last: on the few px at its bottom edge that are a mouse's handle, which a finger does not have
    for (const [where, down] of [['its middle', 0.5], ['70% of the way down it', 0.7], ['its bottom edge', 0.95]]) {
        test(`at ${where} moves it, wherever on it the finger is: put on the day before, it is a move to that day and no resize`, async ({ page }) => {
            const hand = finger(page)
            const from = await inBox(chipOf(page, 'block', 'Team standup'), 0.5, down)
            const to = await dayBefore(page, from)

            await longPress(page, hand, from)

            // the finger is the item's now: the page does not scroll under it
            expect(await hand.move(from, to)).toBe(false)
            await expect(ghosts(page)).toHaveCount(1)
            await expect(column(page, '2026-10-08').locator('[data-atom-calendar-preview]')).toHaveCount(1)
            await hand.up(to)

            expect(await moves(page)).toEqual([{ id: 1, start: '2026-10-08T09:30', end: '2026-10-08T10:00', allDay: false }])
            expect(await resizes(page)).toEqual([])
            await expect(column(page, '2026-10-08').locator('[data-atom-calendar-block]', { hasText: 'Team standup' })).toHaveCount(1)
        })
    }

    test('picks it up: its ghost shows the grips while the finger is down, and the item keeps them once it is let go, where it was put', async ({ page }) => {
        const hand = finger(page)
        const from = await inBox(chipOf(page, 'block', 'Team standup'))
        const to = await dayBefore(page, from)

        await expect(grips(page, 'Team standup')).toHaveCount(0)
        await longPress(page, hand, from)

        // the top and the bottom of it, on the ghost (the item itself is lifted under it)
        await expect(ghosts(page).locator('[data-atom-calendar-grip]:visible')).toHaveCount(2)
        expect(await picked(page)).toBe('1')

        await hand.move(from, to)
        await expect(ghosts(page).locator('[data-atom-calendar-grip]:visible')).toHaveCount(2)
        await hand.up(to)

        await expect(ghosts(page)).toHaveCount(0)

        const moved = column(page, '2026-10-08').locator('[data-atom-calendar-block]', { hasText: 'Team standup' })

        await expect(moved.locator('[data-atom-calendar-grip="start"]')).toBeVisible()
        await expect(moved.locator('[data-atom-calendar-grip="end"]')).toBeVisible()

        // a finger's size, and on the item's top and bottom edges
        const item = await moved.boundingBox()
        const top = await moved.locator('[data-atom-calendar-grip="start"]').boundingBox()
        const bottom = await moved.locator('[data-atom-calendar-grip="end"]').boundingBox()

        expect(top.height).toBeGreaterThanOrEqual(28)
        expect(top.width).toBeGreaterThanOrEqual(28)
        expect(Math.abs(top.y + top.height / 2 - item.y)).toBeLessThan(2)
        expect(Math.abs(bottom.y + bottom.height / 2 - (item.y + item.height))).toBeLessThan(2)

        // decoration for the pointer: the item's name is the same, and the grips are out of the accessibility tree
        await expect(moved.locator('[data-atom-calendar-grips]')).toHaveAttribute('aria-hidden', 'true')
    })

    test('a long press that is let go where it was moves nothing, and leaves the item picked up', async ({ page }) => {
        await pickStandup(page, finger(page))

        expect(await moves(page)).toEqual([])
        expect(await resizes(page)).toEqual([])
        await expect(ghosts(page)).toHaveCount(0)
    })

    test('a tap is still the item\'s click, and picks nothing up', async ({ page }) => {
        const hand = finger(page)

        await hand.tap(await inBox(chipOf(page, 'block', 'Team standup')))

        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
        await expect(grips(page, 'Team standup')).toHaveCount(0)
        expect(await moves(page)).toEqual([])
    })

    test('the bottom grip pulled down changes the end, and the start stays', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        const from = await inBox(grip(page, 'Team standup', 'end'))
        const to = { x: from.x, y: from.y + await hourPx(page) }

        // at once: a grip has no long press to wait for
        await hand.down(from)
        expect(await hand.move(from, to)).toBe(false)
        await expect(ghosts(page)).toHaveCount(1)
        await expect(ghosts(page)).toContainText('11:00')
        await hand.up(to)

        expect(await resizes(page)).toEqual([{ id: 1, start: '2026-10-09T09:30', end: '2026-10-09T11:00' }])
        expect(await moves(page)).toEqual([])

        // still picked up, for another pull
        await expect(grips(page, 'Team standup')).toHaveCount(2)
    })

    test('the top grip pulled up changes the start, and the end stays: the host is told with the resize event, the new start in it', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        const from = await inBox(grip(page, 'Team standup', 'start'))
        const to = { x: from.x, y: from.y - await hourPx(page) }

        await hand.down(from)
        await hand.move(from, to)
        await expect(ghosts(page)).toHaveCount(1)
        await expect(ghosts(page).locator('[data-atom-calendar-start-label]')).toHaveText('8:30 AM')
        await hand.up(to)

        expect(await resizes(page)).toEqual([{ id: 1, start: '2026-10-09T08:30', end: '2026-10-09T10:00' }])
        expect(await moves(page)).toEqual([])
    })

    test('a grip pulled sideways stays on the item\'s day: only the time changes', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        const from = await inBox(grip(page, 'Team standup', 'end'))
        const side = await dayBefore(page, from)
        const to = { x: side.x, y: from.y + await hourPx(page) }

        await hand.down(from)
        await hand.move(from, to)
        // the ghost is on the 9th, not the 8th
        await expect(column(page, '2026-10-09').locator('[data-atom-calendar-preview]')).toHaveCount(1)
        await expect(column(page, '2026-10-08').locator('[data-atom-calendar-preview]')).toHaveCount(0)
        await hand.up(to)

        expect(await resizes(page)).toEqual([{ id: 1, start: '2026-10-09T09:30', end: '2026-10-09T11:00' }])
    })

    test('a bottom grip pulled up past the start stops a slot after it: here that is where it ends already, so nothing is told', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        const from = await inBox(grip(page, 'Team standup', 'end'))
        const to = { x: from.x, y: from.y - 2 * await hourPx(page) }

        await hand.down(from)
        await hand.move(from, to)
        await hand.up(to)

        expect(await resizes(page)).toEqual([])
    })

    test('a press elsewhere puts it down, and so does Escape', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        // a tap on an empty hour of another day
        await hand.tap(await inBox(column(page, '2026-10-08'), 0.5, 3 / 24))
        await expect(grips(page, 'Team standup')).toHaveCount(0)
        expect(await picked(page)).toBeNull()

        await pickStandup(page, hand)
        await page.keyboard.press('Escape')
        await expect(grips(page, 'Team standup')).toHaveCount(0)
    })

    test('a grip that lies over the item below it is the grip\'s, not that item\'s', async ({ page }) => {
        // Planning (09:00 to 10:00) and, under it in its lane, Retro (10:00 to 11:00) on the 27th
        await open(page, 'view=week&date=2026-10-26&busy=1')
        await centre(page, 'Planning')

        const hand = finger(page)
        const point = await inBox(chipOf(page, 'block', 'Planning'))

        await longPress(page, hand, point)
        await hand.up(point)

        const end = await grip(page, 'Planning', 'end').boundingBox()
        const retro = await block(page, 'Retro').boundingBox()
        // the lower half of the grip, which is over Retro
        const over = { x: end.x + end.width / 2, y: end.y + end.height * 0.75 }

        expect(over.y).toBeGreaterThan(retro.y)
        expect(over.x).toBeGreaterThan(retro.x)
        expect(over.x).toBeLessThan(retro.x + retro.width)
        expect(await page.evaluate(([x, y]) => document.elementFromPoint(x, y)?.closest('[data-atom-calendar-grip]')?.getAttribute('data-atom-calendar-grip') ?? null, [over.x, over.y])).toBe('end')
    })

    // The grip is a finger's size, 2rem square, and overhangs the event below a block (the title of it) by half. A tap there is meant for that event: it is
    // a tap on something else, and puts the item down; a press on a grip is only a press on the item when it becomes a pull.
    async function pickPlanning (page, hand) {
        await open(page, 'view=week&date=2026-10-26&busy=1')
        await centre(page, 'Planning')

        const point = await inBox(chipOf(page, 'block', 'Planning'))

        await longPress(page, hand, point)
        await hand.up(point)
        await expect(grips(page, 'Planning')).toHaveCount(2)

        const end = await grip(page, 'Planning', 'end').boundingBox()

        // the lower half of the end grip: over Retro's title, 10:00 to 11:00 under Planning in its lane
        return { x: end.x + end.width / 2, y: end.y + end.height * 0.75 }
    }

    test('a tap on the lower half of a grip, over the event below, puts the item down', async ({ page }) => {
        const hand = finger(page)
        const over = await pickPlanning(page, hand)

        await hand.tap(over)
        await expect(grips(page, 'Planning')).toHaveCount(0)
        expect(await picked(page)).toBeNull()
        expect(await resizes(page)).toEqual([])
        expect(await moves(page)).toEqual([])
    })

    test('the same tap, with the browser\'s own touch, puts it down, and on WebKit the event below gets the click', async ({ page, browserName }) => {
        const hand = finger(page)
        const over = await pickPlanning(page, hand)

        await page.touchscreen.tap(over.x, over.y)
        await expect(grips(page, 'Planning')).toHaveCount(0)
        expect(await picked(page)).toBeNull()
        expect(await resizes(page)).toEqual([])

        // The grips are gone by the time the click is dispatched, so the event under the finger gets it, which is what was meant (asserted on WebKit)
        if (browserName === 'webkit') await expect.poll(() => page.evaluate(() => window.__clicks)).toContain(22)
    })

    test('a grip pulled a little still resizes, and the item stays picked up', async ({ page }) => {
        const hand = finger(page)
        await pickPlanning(page, hand)
        const end = await inBox(grip(page, 'Planning', 'end'))

        await hand.down(end)
        const pulled = { x: end.x, y: end.y + 60 }

        await hand.move(end, pulled, 4)
        await hand.up(pulled)

        expect((await resizes(page)).length).toBe(1)
        expect(await picked(page)).not.toBeNull()
    })

    test('a press on the item or on its grip does not put it down', async ({ page }) => {
        const hand = finger(page)

        await pickStandup(page, hand)

        // the upper half of the end grip, which is over the item (the lower half is over whatever is below it: see the tests of the grip's overhang)
        const box = await grip(page, 'Team standup', 'end').boundingBox()
        const end = { x: box.x + box.width / 2, y: box.y + box.height * 0.25 }

        await hand.down(end)
        await hand.up(end)
        await expect(grips(page, 'Team standup')).toHaveCount(2)
        expect(await resizes(page)).toEqual([])

        await hand.tap(await inBox(chipOf(page, 'block', 'Team standup')))
        await expect(grips(page, 'Team standup')).toHaveCount(2)
    })
})

test.describe('a bar of the all-day row', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'view=week&date=2026-10-05')
        // Launch week, the 5th to the 9th: the start of it on screen, at the left of the grid
        await grid(page).evaluate((el) => { el.scrollLeft = 0 })
    })

    test('is picked up by a long press and has a grip on each end; the end one pulled back a day changes its last day', async ({ page }) => {
        const hand = finger(page)
        const bar = chipOf(page, 'bar', 'Launch week')
        const from = await inBox(bar, 0.2)

        await hand.down(from)
        await expect(probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-ghost]')).toHaveCount(1, { timeout: 1500 })
        await hand.up(from)

        const slot = probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-slot]', { hasText: 'Launch week' })

        await expect(slot.locator('[data-atom-calendar-grip="start"]')).toBeVisible()
        // the end of it is the 9th, off screen to the right: the grid is scrolled to it
        await grid(page).evaluate((el) => { el.scrollLeft = el.scrollWidth })
        await expect(slot.locator('[data-atom-calendar-grip="end"]')).toBeVisible()

        const end = await inBox(slot.locator('[data-atom-calendar-grip="end"]'))
        const day = (await probe(page).locator('[data-atom-calendar-allday-bg] > [data-date="2026-10-08"]').boundingBox()).width

        await hand.down(end)
        await hand.move(end, { x: end.x - day, y: end.y })
        await hand.up({ x: end.x - day, y: end.y })

        expect(await resizes(page)).toEqual([{ id: 2, start: '2026-10-05', end: '2026-10-08' }])
        expect(await moves(page)).toEqual([])
    })

    test('and the start one pulled on a day changes its first day', async ({ page }) => {
        const hand = finger(page)
        const from = await inBox(chipOf(page, 'bar', 'Launch week'), 0.5)

        await hand.down(from)
        await expect(probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-ghost]')).toHaveCount(1, { timeout: 1500 })
        await hand.up(from)

        const slot = probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-slot]', { hasText: 'Launch week' })
        const start = await inBox(slot.locator('[data-atom-calendar-grip="start"]'))
        const day = (await probe(page).locator('[data-atom-calendar-allday-bg] > [data-date="2026-10-06"]').boundingBox()).width

        await hand.down(start)
        await hand.move(start, { x: start.x + day, y: start.y })
        await hand.up({ x: start.x + day, y: start.y })

        expect(await resizes(page)).toEqual([{ id: 2, start: '2026-10-06', end: '2026-10-09' }])
    })

    test('one that cannot be resized is moved, picked up, and shows no grips', async ({ page }) => {
        await open(page, 'view=week&date=2026-10-12&locked=1')

        const bar = chipOf(page, 'bar', 'Fixed length')

        await bar.scrollIntoViewIfNeeded()

        const hand = finger(page)
        const from = await inBox(bar)

        await hand.down(from)
        await expect(probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-ghost]')).toHaveCount(1, { timeout: 1500 })
        await expect(probe(page).locator('[data-atom-calendar-ghost] [data-atom-calendar-grip]')).toHaveCount(0)
        await hand.up(from)

        expect(await picked(page)).toBe('81')
        await expect(probe(page).locator('[data-atom-calendar-grip]')).toHaveCount(0)
    })
})

test.describe('the month on a phone', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('shows dots, and an item is picked up from the day list by a long press: the list closes, and it is put on another day', async ({ page }) => {
        const hand = finger(page)
        const day = probe(page).locator('[data-atom-calendar-day][data-date="2026-10-09"]')
        const list = probe(page).locator('[data-atom-calendar-popover]')

        // a day of dots, no titles: a tap opens the day's list
        await expect(probe(page).locator('[data-atom-calendar-chip]:visible')).toHaveCount(0)
        await hand.tap(await inBox(day, 0.5, 0.8))
        await expect(list).toBeVisible()

        const row = list.locator('[data-atom-calendar-chip]', { hasText: 'Team standup' })

        await expect(row).toHaveAttribute('data-pickable', '')

        const from = await inBox(row)

        await hand.down(from)
        // held: the list goes, and the item's ghost is on its day
        await expect(list).toBeHidden({ timeout: 1500 })
        await expect(probe(page).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')).toHaveCount(1)

        const target = probe(page).locator('[data-atom-calendar-day][data-date="2026-10-14"]')

        await target.scrollIntoViewIfNeeded()

        const to = await inBox(target, 0.5, 0.8)

        expect(await hand.move(from, to)).toBe(false)
        await hand.up(to)

        expect(await moves(page)).toEqual([{ id: 1, start: '2026-10-14T09:30', end: '2026-10-14T10:00', allDay: false }])
        expect(await resizes(page)).toEqual([])
        await expect(probe(page).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')).toHaveCount(0)
    })

    // The finger above is pointer and touch events sent by hand to what the finger pressed, and sent again to what is under the point once that is no
    // longer on the page. A row of the list is taken off the page as the press becomes a drag, and a real touch goes on to the row it began on, so the
    // hand-sent finger hid a drag the browser cancelled (the touchmove never reached the calendar, nothing prevented it, the page panned, and a
    // pointercancel ended the move). This one is the browser's own: touch events from the DevTools protocol, which the engine turns into pointer events.
    // Chromium only (the protocol is); WebKit's real touch is not reachable from here.
    const realFinger = async (page) => {
        const cdp = await page.context().newCDPSession(page)
        const send = (type, points) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points.map(({ x, y }) => ({ x, y, id: 1 })) })

        return {
            down: (point) => send('touchStart', [point]),
            async move (from, to, steps = 12) {
                for (let i = 1; i <= steps; i++) {
                    await send('touchMove', [{ x: from.x + (to.x - from.x) * i / steps, y: from.y + (to.y - from.y) * i / steps }])
                    await page.waitForTimeout(16)
                }
            },
            up: () => send('touchEnd', []),
            cancel: () => send('touchCancel', []),
        }
    }

    async function pressStandupInList (page, hand) {
        const day = probe(page).locator('[data-atom-calendar-day][data-date="2026-10-09"]')
        const list = probe(page).locator('[data-atom-calendar-popover]')

        await finger(page).tap(await inBox(day, 0.5, 0.8))
        await expect(list).toBeVisible()

        const from = await inBox(list.locator('[data-atom-calendar-chip]', { hasText: 'Team standup' }))

        await hand.down(from)
        // held: the list goes, and the item's ghost is on its day
        await expect(list).toBeHidden({ timeout: 1500 })
        await expect(probe(page).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')).toHaveCount(1)

        const target = probe(page).locator('[data-atom-calendar-day][data-date="2026-10-14"]')

        await target.scrollIntoViewIfNeeded()

        return { from, to: await inBox(target, 0.5, 0.8) }
    }

    test.describe('with a real touch (the browser\'s own events)', () => {
        test.skip(({ browserName }) => browserName !== 'chromium', 'the DevTools protocol is Chromium\'s')

        test('the list drag is not cancelled: the item is put on the day the finger is let go over', async ({ page }) => {
            const hand = await realFinger(page)
            const { from, to } = await pressStandupInList(page, hand)

            // the row the finger pressed is gone from the page, and the finger is still its
            await hand.move(from, to)
            await hand.up()

            await expect.poll(() => moves(page)).toEqual([{ id: 1, start: '2026-10-14T09:30', end: '2026-10-14T10:00', allDay: false }])
            expect(await resizes(page)).toEqual([])
            await expect(probe(page).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')).toHaveCount(0)
        })

        test('a pointercancel in the middle of the drag gives the move up: nothing is told, the ghost goes', async ({ page }) => {
            const hand = await realFinger(page)
            const { from, to } = await pressStandupInList(page, hand)

            await page.evaluate(() => { window.__cancels = 0; document.addEventListener('pointercancel', () => window.__cancels++, true) })
            await hand.move(from, to, 4)
            await hand.cancel()

            await expect(probe(page).locator('[data-atom-calendar-week] [data-atom-calendar-ghost]')).toHaveCount(0)
            expect(await page.evaluate(() => window.__cancels)).toBeGreaterThan(0)
            await page.waitForTimeout(300)
            expect(await moves(page)).toEqual([])
        })
    })

    test('a tap on a row of the list is still its click, and a finger that scrolls the list moves nothing', async ({ page }) => {
        const hand = finger(page)
        const list = probe(page).locator('[data-atom-calendar-popover]')

        await hand.tap(await inBox(probe(page).locator('[data-atom-calendar-day][data-date="2026-10-09"]'), 0.5, 0.8))
        await expect(list).toBeVisible()

        const row = list.locator('[data-atom-calendar-chip]', { hasText: 'Team standup' })
        const from = await inBox(row)

        // a finger that moves on before the press is long is scrolling the list
        await hand.down(from)
        expect(await hand.move(from, { x: from.x, y: from.y - 30 }, 3)).toBe(true)
        await page.waitForTimeout(700)
        await hand.up({ x: from.x, y: from.y - 30 })
        await expect(list).toBeVisible()
        expect(await moves(page)).toEqual([])

        await hand.tap(await inBox(row))
        expect(await page.evaluate(() => window.__clicks)).toEqual([1])
    })
})
