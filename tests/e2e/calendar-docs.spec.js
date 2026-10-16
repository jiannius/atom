import { test, expect } from '@playwright/test'

// The calendar's docs page (/atom/docs/calendar): every demo mounts with no console error, and what each one says it shows is there. The demos are
// static (no wire:model) and built from today's date, so nothing here names a date: it reads them from the page.

const calendars = (page) => page.locator('[data-atom-calendar]')
const demo = (page, index) => calendars(page).nth(index)

async function open (page) {
    const problems = []

    page.on('pageerror', (error) => problems.push(error.message))
    page.on('console', (message) => {
        if (message.type() === 'error') problems.push(message.text())
    })

    await page.goto('/atom/docs/calendar')
    await page.waitForFunction(() => window.Alpine !== undefined)
    await expect(calendars(page)).toHaveCount(5)

    // each is started: Alpine has taken the cloak off what is shown
    for (let index = 0; index < 5; index++) {
        await expect(demo(page, index).locator('[data-atom-calendar-body]')).toBeVisible()
        await expect(demo(page, index).locator('[x-cloak]:visible')).toHaveCount(0)
    }

    return problems
}

const engine = (page, index) => demo(page, index).evaluate((root) => {
    const { payload, view } = window.Alpine.$data(root)

    return { view, items: payload.items.length, days: payload.window.days.length }
})

test('the docs page mounts every demo and prints no error', async ({ page }) => {
    const problems = await open(page)

    for (let index = 0; index < 5; index++) {
        const { items, days } = await engine(page, index)

        expect(items).toBeGreaterThan(0)
        expect(days).toBe(42)
    }

    expect(problems).toEqual([])
})

test('the page offers each demo with its own source under it', async ({ page }) => {
    await open(page)

    await expect(page.locator('main pre code', { hasText: '<atom:calendar' })).toHaveCount(5)
    await expect(page.locator('main pre code', { hasText: 'week-start="sunday"' })).toHaveCount(1)
})

test('the month demo draws its items, and a link item is a link', async ({ page }) => {
    await open(page)

    const month = demo(page, 0)

    await expect(month.locator('[data-atom-calendar-week]')).toHaveCount(6)
    await expect(month.locator('[data-atom-calendar-chip="bar"]:visible', { hasText: 'Sprint' }).first()).toBeVisible()
    await expect(month.locator('a[href="/atom/docs"]', { hasText: 'Open the docs' })).toBeVisible()
})

test('the busy demo shows +N more and the whole day opens from it', async ({ page }) => {
    await open(page)

    const busy = demo(page, 1)
    const more = busy.locator('[data-atom-calendar-more]')

    await expect(more).toHaveCount(1)
    await expect(more).toHaveText('+4 more')

    await more.click()

    const list = busy.locator('[data-atom-calendar-popover]')

    await expect(list).toBeVisible()
    await expect(list.locator('[data-atom-calendar-chip]')).toHaveCount(6)

    await page.keyboard.press('Escape')
    await expect(list).toBeHidden()
})

test('the week demo opens in the week view, with the overlapping items side by side', async ({ page }) => {
    await open(page)

    const week = demo(page, 2)

    expect((await engine(page, 2)).view).toBe('week')
    await expect(week.locator('[data-atom-calendar-column]')).toHaveCount(7)

    const blocks = week.locator('[data-atom-calendar-block]', { hasText: /Planning|Design review|Client call/ })

    await expect(blocks).toHaveCount(3)

    const boxes = await blocks.evaluateAll((els) => els.map((el) => el.getBoundingClientRect()).map(({ left, right }) => ({ left, right })))

    // three lanes: each starts where no other does, and none covers the whole column
    expect(new Set(boxes.map((box) => Math.round(box.left))).size).toBe(3)

    // the switcher takes it to the month and back with no request
    await week.getByRole('button', { name: 'Month' }).click()
    await expect(week.locator('[data-atom-calendar-week]').first()).toBeVisible()
    expect((await engine(page, 2)).view).toBe('month')
})

test('the gestures demo logs what a click, a move, a resize and a selection say', async ({ page }) => {
    await open(page)

    // The rig ships no Tailwind, so a block's chip (`flex h-full w-full flex-col`) is an inline link and its 6px resize handle sits at the
    // bottom of one line of text: where that is depends on the font's metrics, so a press 10px into the block could land on the handle
    // (a resize, not a move) on one OS and not another. Tailwind's own definitions of the utilities the chip leans on, as calendar.blade.php
    // has them; they name no calendar element, the markup's class list decides.
    await page.addStyleTag({
        content: `
            *, ::before, ::after { box-sizing: border-box }
            .flex { display: flex }
            .flex-col { flex-direction: column }
            .h-full { height: 100% }
            .w-full { width: 100% }
            .min-w-0 { min-width: 0 }
            .overflow-hidden { overflow: hidden }
        `,
    })

    const gestures = demo(page, 3)
    const log = page.locator('[data-calendar-log]')

    await gestures.scrollIntoViewIfNeeded()
    await expect(log).toHaveText('Move, resize, select or click something.')

    // a click on an item
    await gestures.locator('[data-atom-calendar-chip]:visible', { hasText: 'Offsite' }).click()
    await expect(log).toContainText('"event":"calendar-item-click"')
    await expect(log).toContainText('"id":1')

    // a drag of a block to the day after it (the rig has no Tailwind, so the week is wider than the page: the left of it is what is reached): the details are the viewer's own time, as the payload writes it
    const block = gestures.locator('[data-atom-calendar-block]', { hasText: 'Design review' })
    const from = await block.boundingBox()
    const next = await gestures.locator('[data-atom-calendar-column]').nth(1).boundingBox()

    // the press lands on the block's body, not on its resize handle: a change of font or layout fails here, not as a missing "move" below
    const insideHandle = await page.evaluate(([x, y]) => Boolean(document.elementFromPoint(x, y)?.closest('[data-atom-calendar-handle]')), [from.x + from.width / 2, from.y + 10])
    expect(insideHandle, 'the press point is inside a resize handle').toBe(false)

    await page.mouse.move(from.x + from.width / 2, from.y + 10)
    await page.mouse.down()
    await page.mouse.move(next.x + next.width / 2, from.y + 10, { steps: 10 })
    await page.mouse.up()

    await expect(log).toContainText('"event":"calendar-item-move"')
    await expect(log).toContainText(/"id":2,"start":"\d{4}-\d\d-\d\dT10:00","end":"\d{4}-\d\d-\d\dT11:30","allDay":false/)

    // the end of a block pulled down one hour
    const handle = gestures.locator('[data-atom-calendar-block]', { hasText: 'Client call' }).locator('[data-atom-calendar-handle]:visible')
    const grip = await handle.boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

    await page.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2)
    await page.mouse.down()
    await page.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2 + 3.5 * rem, { steps: 10 })
    await page.mouse.up()

    await expect(log).toContainText('"event":"calendar-item-resize"')
    await expect(log).toContainText(/"id":3,"start":"\d{4}-\d\d-\d\dT11:00","end":"\d{4}-\d\d-\d\dT13:00"/)

    // a drag over empty space, in the Monday column, where the block was
    const monday = await gestures.locator('[data-atom-calendar-column]').first().boundingBox()

    await page.mouse.move(monday.x + monday.width / 2, from.y + 10)
    await page.mouse.down()
    await page.mouse.move(monday.x + monday.width / 2, from.y + 10 + 1.75 * rem * 2, { steps: 10 })
    await page.mouse.up()

    await expect(log).toContainText('"event":"calendar-select"')
    await expect(log).toContainText(/"allDay":false/)

    // the locked item has no handle and is not picked up
    await expect(gestures.locator('[data-atom-calendar-block]', { hasText: 'Locked item' }).locator('[data-atom-calendar-handle]:visible')).toHaveCount(0)
})

test('the Sunday demo starts its weeks on Sunday and tints Friday and Saturday', async ({ page }) => {
    await open(page)

    const sunday = demo(page, 4)

    await expect(sunday.locator('[data-atom-calendar-weekdays] [role="columnheader"]').first()).toHaveText('Sun')

    // the weekend of the payload is the two days the demo asks for, whatever the week starts on
    const weekend = await sunday.evaluate((root) => [...new Set(window.Alpine.$data(root).payload.window.days.filter((day) => day.weekend).map((day) => day.weekday))].sort())

    expect(weekend).toEqual(['friday', 'saturday'])

    const first = await sunday.locator('[data-atom-calendar-day]').first().getAttribute('data-date')

    expect(new Date(first + 'T00:00:00Z').getUTCDay()).toBe(0)

    // a 24-hour time, as time-format says
    await expect(sunday.locator('[data-atom-calendar-chip="dot"]', { hasText: 'Stock take' })).toContainText('08:30')
})
