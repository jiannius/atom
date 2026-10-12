import { test, expect } from '@playwright/test'

// The wiring the README shows: x-on attributes on the <atom:calendar> tag that call $wire methods and hand their promise to waitUntil.
// CalendarDocsTest holds the fixture's tag to the README's Quick start line by line, and its methods to the README class's. The Livewire
// fixture (/atom/e2e/calendar?wired=1, frozen at 2026-10-09 10:30 in Kuala Lumpur) has the three actions the README's example has, create(),
// reschedule() and edit(), and shows what they were given. No host script on the page answers: only the tag does.

const HOUR = 3.5

const calendar = (page) => page.locator('[data-probe="calendar"]')
const column = (page, date) => calendar(page).locator(`[data-atom-calendar-column][data-date="${date}"]`)
const block = (page, text) => calendar(page).locator('[data-atom-calendar-block]', { hasText: text })
const rescheduled = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.Livewire.first().rescheduled)))
const created = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.Livewire.first().created)))

async function open (page, query = '') {
    await page.goto('/atom/e2e/calendar?wired=1&view=week&date=2026-10-14' + query)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(calendar(page).locator('[data-atom-calendar-body]')).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)
    await expect(column(page, '2026-10-14')).toBeVisible()
}

async function drag (page, from, to) {
    await page.mouse.move(from.x, from.y)
    await page.mouse.down()
    await page.mouse.move(to.x, to.y, { steps: 10 })
    await page.mouse.up()
}

test('a move handled by x-on on the tag reaches the server, in the viewer\'s time, and the item is where the server has it', async ({ page }) => {
    await open(page)

    // Design review, 14 October 10:00 to 11:00; dropped on the 15th an hour later
    const from = await block(page, 'Design review').boundingBox()
    const next = await column(page, '2026-10-15').boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

    await drag(page, { x: from.x + from.width / 2, y: from.y + 10 }, { x: next.x + next.width / 2, y: from.y + 10 + HOUR * rem })

    // what the action was given is the viewer's wall clock, and Calendar::fromLocal() made the instant (Kuala Lumpur is +8)
    await expect.poll(() => rescheduled(page)).toEqual([
        { id: 6, start: '2026-10-15T11:00', end: '2026-10-15T12:00', startUtc: '2026-10-15T03:00:00Z' },
    ])

    // the payload that followed the re-render has it there
    await expect(column(page, '2026-10-15').locator('[data-atom-calendar-block]', { hasText: 'Design review' })).toBeVisible()
    await expect(column(page, '2026-10-14').locator('[data-atom-calendar-block]', { hasText: 'Design review' })).toHaveCount(0)
    await expect(calendar(page).locator('[data-atom-calendar-announce]')).toHaveText('')
})

test('a resize handled the same way sends the new end', async ({ page }) => {
    await open(page)

    // Dentist, 08:00 to 09:00, pulled down an hour by the handle on its end (high enough in the grid that the page does not scroll under the pointer)
    const handle = block(page, 'Dentist').locator('[data-atom-calendar-handle]:visible')
    const grip = await handle.boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

    await drag(page, { x: grip.x + grip.width / 2, y: grip.y + grip.height / 2 }, { x: grip.x + grip.width / 2, y: grip.y + grip.height / 2 + HOUR * rem })

    await expect.poll(() => rescheduled(page)).toEqual([
        { id: 5, start: '2026-10-14T08:00', end: '2026-10-14T10:00', startUtc: '2026-10-14T00:00:00Z' },
    ])
})

test('a selection handled by x-on creates on the server, and the new item comes with the render and the placeholder is gone', async ({ page }) => {
    await open(page)

    // an empty stretch of the 15th: from 08:00 over the slot that holds 09:00 (a selection takes whole slots, so it ends at 09:30)
    const day = await column(page, '2026-10-15').boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))
    const x = day.x + day.width / 2

    await drag(page, { x, y: day.y + 8 * HOUR * rem + 4 }, { x, y: day.y + 9 * HOUR * rem + 4 })

    await expect.poll(() => created(page)).toHaveLength(1)

    const [row] = await created(page)

    expect(row).toMatchObject({ start: '2026-10-15T08:00', end: '2026-10-15T09:30', allDay: false, startUtc: '2026-10-15T00:00:00Z', endUtc: '2026-10-15T01:30:00Z' })

    await expect(column(page, '2026-10-15').locator('[data-atom-calendar-block]', { hasText: 'Created' })).toBeVisible()
    await expect(calendar(page).locator('[data-atom-calendar-pending]')).toHaveCount(0)
})

test('a click on an item handled by x-on on the tag calls edit() with its id, and a drag does not', async ({ page }) => {
    await open(page)

    const edited = () => page.evaluate(() => JSON.parse(JSON.stringify(window.Livewire.first().edited)))

    // Dentist, id 5: a plain click, and the keyboard's way (Enter on a focused block)
    await block(page, 'Dentist').click()
    await expect.poll(edited).toEqual([5])

    await block(page, 'Lunch with Sam').locator('[data-atom-calendar-chip]').focus()
    await page.keyboard.press('Enter')
    await expect.poll(edited).toEqual([5, 7])

    // a drag of an item moves it and is not a click
    const from = await block(page, 'Design review').boundingBox()
    const next = await column(page, '2026-10-15').boundingBox()

    await drag(page, { x: from.x + from.width / 2, y: from.y + 10 }, { x: next.x + next.width / 2, y: from.y + 10 })
    await expect.poll(() => rescheduled(page)).toHaveLength(1)
    expect(await edited()).toEqual([5, 7])
})

// Livewire does not reject the promise of an action that fails validation: it catches the exception on the server, answers 200 and resolves null. So
// the README's tag turns a save that did not return true into a rejection with `.then((saved) => saved || Promise.reject())`, and the item goes back.
// The fixture's `?strict=1` makes reschedule() run a validation rule that a timed start breaks. Run against the tag as it was (without the `.then`) this
// test fails: the item stays on the 15th.
test('a move whose action fails validation goes back, with nothing stored and no error modal', async ({ page }) => {
    await open(page, '&strict=1')

    const from = await block(page, 'Design review').boundingBox()
    const next = await column(page, '2026-10-15').boundingBox()
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))
    const answered = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/update'))

    await drag(page, { x: from.x + from.width / 2, y: from.y + 10 }, { x: next.x + next.width / 2, y: from.y + 10 + HOUR * rem })

    // the request was made, and answered normally: validation is not an HTTP error
    expect((await answered).status()).toBe(200)

    // nothing was stored, and the item is back on its original day
    expect(await rescheduled(page)).toEqual([])
    expect(await page.evaluate(() => window.Livewire.first().placed)).toEqual([])
    await expect(column(page, '2026-10-14').locator('[data-atom-calendar-block]', { hasText: 'Design review' })).toBeVisible()
    await expect(column(page, '2026-10-15').locator('[data-atom-calendar-block]', { hasText: 'Design review' })).toHaveCount(0)

    // the engine let go of it, and the screen reader line for a save in flight is empty
    await expect.poll(() => calendar(page).evaluate((root) => Object.keys(window.Alpine.$data(root).overrides))).toEqual([])
    await expect(calendar(page).locator('[data-atom-calendar-announce]')).toHaveText('')

    // a rejection from validation needs no error page: that is what a thrown exception or an abort() would have shown
    await expect(page.locator('#livewire-error')).toHaveCount(0)
})
