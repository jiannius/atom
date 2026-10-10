import { test, expect } from '@playwright/test'

// The week and day views, in a browser. Two pages: the Livewire fixture (/atom/e2e/calendar, a bound anchor date,
// frozen at 2026-10-09 10:30 in Kuala Lumpur, so the window for October is 2026-09-28 to 2026-11-08) and a static
// page (/atom/e2e/calendar-grid) whose viewer's timezone and clock the query string sets, for the days the clocks
// change. ?busy=1 adds a crowded week (26 October) to the fixture.
//
// The rig has no Tailwind. What these tests measure is what atom.css owns: the hour height (3rem), the lanes
// and the blocks' place in the day, the sticky header and gutter, the horizontal scroll. Colour and type are looked at
// in the screenshots.

const probe = (page, name = 'calendar') => page.locator(`[data-probe="${name}"]`)
const grid = (page, name) => probe(page, name).locator('[data-atom-calendar-grid]')
const columns = (page, name) => probe(page, name).locator('[data-atom-calendar-column]')
const column = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-column][data-date="${date}"]`)
const block = (page, title, name) => probe(page, name).locator('[data-atom-calendar-block]', { hasText: title })
const chipOf = (page, title, name) => probe(page, name).locator('[data-atom-calendar-chip="block"]', { hasText: title })
const nowLine = (page, name) => probe(page, name).locator('[data-atom-calendar-now-line]')
const title = (page, name) => probe(page, name).locator('[role="status"]')
const button = (page, label, name) => probe(page, name).getByRole('button', { name: label, exact: true })

const isUpdate = (response) => response.url().includes('livewire') && response.request().method() === 'POST'

function countRequests (page) {
    const counter = { count: 0 }

    page.on('request', (request) => {
        if (request.url().includes('livewire') && request.method() === 'POST') counter.count++
    })

    return counter
}

async function open (page, query = '', path = '/atom/e2e/calendar') {
    await page.goto(path + (query ? '?' + query : ''))
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)

    // what a click on an item tells the page around the calendar
    await page.evaluate(() => {
        window.__clicks = []
        document.addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail.id))
    })
}

async function openGrid (page, query = '') {
    await open(page, query, '/atom/e2e/calendar-grid')
}

// the box of a block inside its column, as a share of the column's height and width
async function place (page, name, date, calendar) {
    const col = await column(page, date, calendar).boundingBox()
    const box = await block(page, name, calendar).boundingBox()

    return {
        top: (box.y - col.y) / col.height,
        height: box.height / col.height,
        left: (box.x - col.x) / col.width,
        width: box.width / col.width,
        box,
        col,
    }
}

// what a rem is in this page (atom.css sets the root font size), for the measures that are written in rem
const rem = (page) => page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

const intersects = (a, b) => a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5

test.describe('the views', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('the week view shows the seven days of the anchor\'s week, names them, and marks today', async ({ page }) => {
        const requests = countRequests(page)

        await button(page, 'Week').click()

        await expect(columns(page)).toHaveCount(7)
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-05')
        await expect(columns(page).last()).toHaveAttribute('data-date', '2026-10-11')
        await expect(probe(page).locator('[data-atom-calendar-column-head]')).toHaveCount(7)
        await expect(probe(page).locator('[data-atom-calendar-column-head]').first()).toContainText('Mon')
        await expect(title(page)).toHaveText('5 – 11 October 2026')
        await expect(column(page, '2026-10-09')).toHaveAttribute('aria-current', 'date')
        await expect(probe(page).locator('[data-atom-calendar-column][aria-current="date"]')).toHaveCount(1)
        await expect(button(page, 'Week')).toHaveAttribute('aria-pressed', 'true')
        await expect(grid(page)).toBeVisible()
        await expect(probe(page).locator('[role="grid"]')).toBeHidden()

        // no round trip: the week is in the window the server built
        expect(requests.count).toBe(0)
    })

    test('the day view shows the anchor alone', async ({ page }) => {
        const requests = countRequests(page)

        await button(page, 'Day').click()

        await expect(columns(page)).toHaveCount(1)
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-09')
        await expect(title(page)).toHaveText('9 October 2026')
        expect(requests.count).toBe(0)
    })

    test('a column is labelled with its date and how many items it holds', async ({ page }) => {
        await button(page, 'Week').click()

        await expect(column(page, '2026-10-09')).toHaveAttribute('aria-label', 'Friday, 9 Oct 2026, 3 items')
        await expect(column(page, '2026-10-09')).toHaveAttribute('role', 'group')
        await expect(column(page, '2026-10-06')).toHaveAttribute('aria-label', 'Tuesday, 6 Oct 2026, 2 items')
    })

    test('switching view keeps the day on screen: week, day, and back to the same month with no request', async ({ page }) => {
        const requests = countRequests(page)

        await button(page, 'Week').click()
        await button(page, 'Next').click()
        await expect(title(page)).toHaveText('12 – 18 October 2026')

        // the anchor is now 16 October
        await button(page, 'Day').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-16')
        await expect(title(page)).toHaveText('16 October 2026')

        await button(page, 'Week').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-12')

        await button(page, 'Month').click()
        await expect(title(page)).toHaveText('October 2026')
        await expect(probe(page).locator('[role="grid"]')).toBeVisible()
        await expect(grid(page)).toBeHidden()

        expect(requests.count).toBe(0)
    })

    test('going back to the month view from a week in another month asks for that month', async ({ page }) => {
        await button(page, 'Week').click()

        // 9 October + four weeks is 6 November: still inside the window the server built around October
        for (let i = 0; i < 4; i++) await button(page, 'Next').click()
        await expect(title(page)).toHaveText('2 – 8 November 2026')

        const requests = countRequests(page)

        await button(page, 'Month').click()
        await page.waitForResponse(isUpdate)

        await expect(title(page)).toHaveText('November 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-11-06')
        expect(requests.count).toBe(1)

        // and the week of the anchor is where it was
        await button(page, 'Week').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-11-02')
    })

    test('the title of a week over two months', async ({ page }) => {
        await button(page, 'Week').click()

        await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).navigate('2026-10-30'))
        await expect(title(page)).toHaveText('26 Oct – 1 Nov 2026')
    })
})

test.describe('navigating the week and the day', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('Next and Previous step a week in the week view, with no request inside the window', async ({ page }) => {
        const requests = countRequests(page)

        await button(page, 'Week').click()
        await button(page, 'Next').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-12')
        await expect(title(page)).toHaveText('12 – 18 October 2026')

        await button(page, 'Previous').click()
        await button(page, 'Previous').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-09-28')
        await expect(title(page)).toHaveText('28 Sep – 4 Oct 2026')

        expect(requests.count).toBe(0)
        // the host's property is kept in step, silently: the next request carries it
        await expect(page.locator('span[data-date]')).toHaveText('2026-10-09')
    })

    test('Next steps a day in the day view', async ({ page }) => {
        await button(page, 'Day').click()
        await button(page, 'Next').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-10')
        await button(page, 'Previous').click()
        await button(page, 'Previous').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-08')

        await button(page, 'Today').click()
        await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-09')
    })

    test('stepping out of the window asks the server for the window around the new date, once', async ({ page }) => {
        await button(page, 'Week').click()

        // 9 October + 35 days is 13 November: past 8 November, the end of the window
        for (let i = 0; i < 4; i++) await button(page, 'Next').click()

        const requests = countRequests(page)

        await button(page, 'Next').click()
        await page.waitForResponse(isUpdate)

        await expect(title(page)).toHaveText('9 – 15 November 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-11-13')
        expect(requests.count).toBe(1)
    })

    test('two quick clicks on Next go two weeks, not the same week twice', async ({ page }) => {
        await page.route(/livewire.*update/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 400))
            await route.continue()
        })

        await button(page, 'Week').click()

        // inside the window to start with, so the first click leaves it and the second steps on from where that one is heading
        await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).navigate('2026-11-05'))
        await expect(title(page)).toHaveText('2 – 8 November 2026')

        await button(page, 'Next').dblclick()

        await expect(title(page)).toHaveText('16 – 22 November 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-11-19')
    })

    test('the date picker in the title jumps to a day inside the window without a request', async ({ page }) => {
        await button(page, 'Week').click()

        const requests = countRequests(page)

        await probe(page).getByRole('button', { name: 'Choose date' }).click()
        await expect(probe(page).locator('.pika-lendar')).toBeVisible()

        await page.evaluate(() => {
            const picker = document.querySelector('[data-probe="calendar"] [data-atom-date-picker]')

            window.Alpine.$data(picker).pikaday.gotoDate(new Date(2026, 9, 22))
        })

        await probe(page).locator('.pika-button[data-pika-year="2026"][data-pika-month="9"][data-pika-day="22"]').click()

        await expect(title(page)).toHaveText('19 – 25 October 2026')
        expect(requests.count).toBe(0)
    })
})

test.describe('where things sit in the grid', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, 'view=week&date=2026-10-27&busy=1')
        await expect(columns(page)).toHaveCount(7)
    })

    test('an hour is 3rem tall, and the gutter has a label for each', async ({ page }) => {
        const hours = probe(page).locator('[data-atom-calendar-hour]')

        await expect(hours).toHaveCount(24)

        const a = await hours.nth(7).boundingBox()
        const b = await hours.nth(8).boundingBox()

        expect(b.y - a.y).toBeCloseTo(3 * await rem(page), 0)
        await expect(probe(page).locator('[data-atom-calendar-hour-label]').nth(13)).toHaveText('1 PM')
        await expect(probe(page).locator('[data-atom-calendar-hour-label]').nth(12)).toHaveText('12 PM')
        await expect(probe(page).locator('[data-atom-calendar-hour-label]').nth(0)).toHaveText('')
        await expect(probe(page).locator('[data-atom-calendar-hour-label]').nth(23)).toHaveText('11 PM')
    })

    test('a block is where its time says: start and length are shares of the day', async ({ page }) => {
        const solo = await place(page, 'Solo', '2026-10-27')

        expect(solo.top).toBeCloseTo(13 / 24, 2)
        expect(solo.height).toBeCloseTo(1 / 24, 2)

        // and in pixels: 13 hours of 3rem below the top of the column
        expect(solo.box.y - solo.col.y).toBeCloseTo(13 * 3 * await rem(page), 0)
    })

    test('overlapping items sit side by side and never cover each other', async ({ page }) => {
        const names = ['Planning', 'Review', 'Retro', 'Solo', 'Alpha', 'Beta', 'Gamma']
        const boxes = {}

        for (const name of names) boxes[name] = await block(page, name).boundingBox()

        for (const a of names) {
            for (const b of names) {
                if (a < b) expect(intersects(boxes[a], boxes[b]), `${a} covers ${b}`).toBe(false)
            }
        }

        // two in a cluster take half the column each; three take a third; one alone takes all of it
        const planning = await place(page, 'Planning', '2026-10-27')
        const review = await place(page, 'Review', '2026-10-27')
        const retro = await place(page, 'Retro', '2026-10-27')
        const solo = await place(page, 'Solo', '2026-10-27')
        const alpha = await place(page, 'Alpha', '2026-10-27')
        const gamma = await place(page, 'Gamma', '2026-10-27')

        expect(planning.width).toBeCloseTo(0.5, 2)
        expect(review.left).toBeCloseTo(0.5, 2)
        // Retro starts as Planning ends, so it goes under it in the first column, though it overlaps Review
        expect(retro.left).toBeCloseTo(0, 2)
        expect(retro.width).toBeCloseTo(0.5, 2)
        expect(solo.width).toBeCloseTo(1, 1)
        expect(alpha.width).toBeCloseTo(1 / 3, 2)
        expect(gamma.left).toBeCloseTo(2 / 3, 2)
    })

    test('every one of the overlapping items can be clicked', async ({ page }) => {
        for (const name of ['Planning', 'Review', 'Retro', 'Alpha', 'Beta', 'Gamma']) {
            await chipOf(page, name).click()
        }

        expect(await page.evaluate(() => window.__clicks)).toEqual([20, 21, 22, 24, 25, 26])
    })

    test('an item with no end is one slot tall; a short one is never under half an hour; none runs past the day', async ({ page }) => {
        // Late call has no end and starts at 23:50
        const late = await place(page, 'Late call', '2026-10-28')

        expect(late.box.y + late.box.height).toBeLessThanOrEqual(late.col.y + late.col.height + 0.5)
        expect(late.box.height).toBeGreaterThanOrEqual(1.5 * await rem(page) - 0.5)

        // it is drawn up from the end of the day, over where Evening wrap (23:00 to 23:45) is, so the two sit side by side
        const evening = await place(page, 'Evening wrap', '2026-10-28')

        expect(intersects(late.box, evening.box)).toBe(false)
        expect(late.left).not.toBeCloseTo(evening.left, 2)

        // Quick call lasts ten minutes (seven pixels of day), drawn half an hour tall
        const quick = await place(page, 'Quick call', '2026-10-28')

        expect(quick.box.height).toBeCloseTo(1.5 * await rem(page), 0)

        await page.goto('/atom/e2e/calendar?view=week&date=2026-10-14')
        await expect(columns(page)).toHaveCount(7)

        // Call the bank, 15:00 with no end: one 30 minute slot (1.5rem); Dentist, 08:00 to 09:00: an hour (3rem)
        const bank = await place(page, 'Call the bank', '2026-10-14')
        const dentist = await place(page, 'Dentist', '2026-10-14')

        expect(bank.box.height).toBeCloseTo(1.5 * await rem(page), 0)
        expect(dentist.box.height).toBeCloseTo(3 * await rem(page), 0)
        expect(bank.top).toBeCloseTo(15 / 24, 2)
    })

    test('a short block prints its time beside the title, a longer one under it', async ({ page }) => {
        await expect(chipOf(page, 'Quick call')).toHaveAttribute('data-compact', '')
        await expect(chipOf(page, 'Quick call')).toContainText('· 11:00 AM')
        await expect(chipOf(page, 'Planning')).not.toHaveAttribute('data-compact', '')
        await expect(chipOf(page, 'Planning')).toContainText('9:00 AM – 10:00 AM')
    })

    test('the all-day row has the all-day items in lanes, and "+N more" past three', async ({ page }) => {
        const row = probe(page).locator('[data-atom-calendar-allday-chips]')

        await expect(row.locator('[data-atom-calendar-chip="bar"]')).toHaveCount(3)
        await expect(row.locator('[data-atom-calendar-more]')).toHaveText('+1 more')

        // Offsite spans Tuesday to Thursday: three columns wide
        const offsite = await row.locator('[data-atom-calendar-chip="bar"]', { hasText: 'Offsite' }).boundingBox()
        const tuesday = await column(page, '2026-10-27').boundingBox()

        expect(offsite.width).toBeGreaterThan(tuesday.width * 2.5)
        await expect(probe(page).getByText('All day', { exact: true })).toBeVisible()
    })

    test('"+N more" in the all-day row opens the day list, next to itself', async ({ page }) => {
        const more = probe(page).locator('[data-atom-calendar-allday-chips] [data-atom-calendar-more]')

        await more.click()

        const popover = probe(page).locator('[data-atom-calendar-popover]')

        await expect(popover).toBeVisible()
        await expect(popover.getByText('Audit')).toBeVisible()
        await expect(popover.getByText('Late call')).toBeVisible()

        const trigger = await more.boundingBox()
        const panel = await popover.boundingBox()

        expect(Math.abs(panel.y - (trigger.y + trigger.height))).toBeLessThan(24)
    })

    test('the grid opens at scroll-time, with that hour just under the header that stays on top', async ({ page }) => {
        const scroller = await grid(page).boundingBox()
        const top = await probe(page).locator('[data-atom-calendar-grid-top]').boundingBox()
        const six = await probe(page).locator('[data-atom-calendar-line]').nth(6).boundingBox()

        // 8px under the header, so the label on the line is not cut in half
        expect(six.y - (top.y + top.height)).toBeGreaterThan(6)
        expect(six.y - (top.y + top.height)).toBeLessThan(10)
        expect(scroller.height).toBeGreaterThan(300)
    })

    test('the header stays on top while the grid scrolls down', async ({ page }) => {
        await grid(page).evaluate((el) => { el.scrollTop = 400 })

        const scroller = await grid(page).boundingBox()
        const top = await probe(page).locator('[data-atom-calendar-grid-top]').boundingBox()

        expect(top.y).toBeCloseTo(scroller.y, 0)
    })

    test('a view with nothing in the hours the user scrolled to still shows a column for each day', async ({ page }) => {
        await expect(column(page, '2026-10-31')).toBeVisible()
        await expect(column(page, '2026-11-01')).toBeVisible()
        await expect(probe(page).locator('[data-atom-calendar-column-head]').nth(5)).toContainText('Sat')
    })
})

test.describe('scroll-time', () => {
    test('opens the grid at the hour it is given', async ({ page }) => {
        await openGrid(page, 'scroll=13:00')

        const top = await probe(page, 'grid').locator('[data-atom-calendar-grid-top]').boundingBox()
        const one = await probe(page, 'grid').locator('[data-atom-calendar-line]').nth(13).boundingBox()

        expect(one.y - (top.y + top.height)).toBeGreaterThan(6)
        expect(one.y - (top.y + top.height)).toBeLessThan(10)
    })

    test('opens at it again when the view comes back from the month', async ({ page }) => {
        await openGrid(page, 'scroll=13:00')
        await grid(page, 'grid').evaluate((el) => { el.scrollTop = 0 })

        await button(page, 'Month', 'grid').click()
        await button(page, 'Week', 'grid').click()

        const top = await probe(page, 'grid').locator('[data-atom-calendar-grid-top]').boundingBox()

        await expect.poll(async () => (await probe(page, 'grid').locator('[data-atom-calendar-line]').nth(13).boundingBox()).y - (top.y + top.height)).toBeLessThan(10)
    })
})

test.describe('the now line', () => {
    test('stands in today\'s column at the time the server said it was, and nowhere else', async ({ page }) => {
        await open(page, 'view=week')

        await expect(nowLine(page)).toHaveCount(1)
        await expect(column(page, '2026-10-09').locator('[data-atom-calendar-now-line]')).toHaveCount(1)
        await expect(nowLine(page)).toHaveAttribute('data-time', '10:30 AM')

        const line = await nowLine(page).boundingBox()
        const col = await column(page, '2026-10-09').boundingBox()

        expect((line.y + line.height / 2 - col.y) / col.height).toBeCloseTo(10.5 / 24, 2)
        expect(line.height).toBeCloseTo(2, 0)
    })

    test('is not in a week without today, and not in the month view', async ({ page }) => {
        await open(page, 'view=week')

        await button(page, 'Next').click()
        await expect(nowLine(page)).toHaveCount(0)

        await button(page, 'Previous').click()
        await expect(nowLine(page)).toHaveCount(1)

        await button(page, 'Month').click()
        await expect(nowLine(page)).toHaveCount(0)
    })

    test('can be switched off', async ({ page }) => {
        await openGrid(page, 'indicator=0')

        await expect(columns(page, 'grid')).toHaveCount(7)
        await expect(nowLine(page, 'grid')).toHaveCount(0)
    })

    test('a render that only moves the clock lays nothing out again and leaves the line where it was', async ({ page }) => {
        await open(page, 'view=week')
        await expect(nowLine(page)).toHaveCount(1)

        await page.evaluate(() => {
            const engine = window.Alpine.$data(document.querySelector('[data-probe="calendar"]'))
            const read = engine.read

            window.__reads = 0
            engine.read = function () {
                window.__reads++

                return read.apply(this, arguments)
            }
        })

        const attribute = await probe(page).getAttribute('data-atom-calendar-now')

        for (let i = 0; i < 2; i++) {
            const renders = page.locator('[data-renders]')
            const before = Number(await renders.textContent())

            await page.locator('[data-bump]').click()
            await expect(renders).toHaveText(String(before + 1))
        }

        await page.waitForTimeout(200)

        // the clock attribute moved with the renders, and nothing read it back
        expect(await probe(page).getAttribute('data-atom-calendar-now')).not.toBe(attribute)
        expect(await page.evaluate(() => window.__reads)).toBe(0)
        await expect(nowLine(page)).toHaveAttribute('data-time', '10:30 AM')
    })

    test('moves with the minute, carried on by the browser\'s own clock', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-10-09T02:30:00Z') })
        await openGrid(page)

        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '10:30 AM')

        await page.clock.runFor('05:00')
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '10:35 AM')

        const line = await nowLine(page, 'grid').boundingBox()
        const col = await column(page, '2026-10-09', 'grid').boundingBox()

        expect((line.y + line.height / 2 - col.y) / col.height).toBeCloseTo((10 * 60 + 35) / 1440, 2)
    })

    test('crosses midnight into the next day\'s column', async ({ page }) => {
        // 23:59:30 in Kuala Lumpur
        await page.clock.install({ time: new Date('2026-10-09T15:59:30Z') })
        await openGrid(page, 'now=2026-10-09T15:59:30Z')

        await expect(column(page, '2026-10-09', 'grid').locator('[data-atom-calendar-now-line]')).toHaveCount(1)

        await page.clock.runFor('01:00')

        await expect(column(page, '2026-10-09', 'grid').locator('[data-atom-calendar-now-line]')).toHaveCount(0)
        await expect(column(page, '2026-10-10', 'grid').locator('[data-atom-calendar-now-line]')).toHaveCount(1)
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '12:00 AM')
    })
})

test.describe('a day the clocks change, for a viewer in Los Angeles', () => {
    const spring = 'tz=America/Los_Angeles&scenario=dst-spring&date=2026-03-08&view=day&now=2026-03-08T11:30:00Z&scroll=00:00'
    const fall = 'tz=America/Los_Angeles&scenario=dst-fall&date=2026-11-01&view=day&now=2026-11-01T09:30:00Z&scroll=00:00'

    test('spring forward: the grid keeps its 24 rows, an item over the gap is as tall as the wall clock says, and the line follows the wall clock', async ({ page }) => {
        await openGrid(page, spring)

        await expect(title(page, 'grid')).toHaveText('8 March 2026')
        await expect(probe(page, 'grid').locator('[data-atom-calendar-hour]')).toHaveCount(24)

        // the hour that does not exist is a row like any other
        const labels = probe(page, 'grid').locator('[data-atom-calendar-hour-label]')

        await expect(labels.nth(2)).toHaveText('2 AM')
        await expect(labels.nth(3)).toHaveText('3 AM')

        // 01:30 to 03:30 on the wall clock: two hours tall, though it lasted one
        const across = await place(page, 'Across the gap', '2026-03-08', 'grid')

        expect(across.top).toBeCloseTo(90 / 1440, 3)
        expect(across.height).toBeCloseTo(120 / 1440, 3)

        const after = await place(page, 'After the gap', '2026-03-08', 'grid')

        expect(after.top).toBeCloseTo(180 / 1440, 3)

        // 11:30 UTC is 04:30 PDT
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '4:30 AM')

        const line = await nowLine(page, 'grid').boundingBox()
        const col = await column(page, '2026-03-08', 'grid').boundingBox()

        expect((line.y + line.height / 2 - col.y) / col.height).toBeCloseTo(270 / 1440, 3)
    })

    test('fall back: the repeated hour is one row, with both passes side by side and the line at the same place', async ({ page }) => {
        await openGrid(page, fall)

        await expect(title(page, 'grid')).toHaveText('1 November 2026')
        await expect(probe(page, 'grid').locator('[data-atom-calendar-hour]')).toHaveCount(24)

        const first = await place(page, 'First pass', '2026-11-01', 'grid')
        const second = await place(page, 'Second pass', '2026-11-01', 'grid')

        expect(first.top).toBeCloseTo(90 / 1440, 3)
        expect(second.top).toBeCloseTo(90 / 1440, 3)
        // 01:30 PDT to 01:30 PST is an hour, and on the wall clock nothing: one slot, which overlaps both
        const hour = await place(page, 'The repeated hour', '2026-11-01', 'grid')

        expect(hour.box.height).toBeCloseTo(1.5 * await rem(page), 0)
        expect(hour.top).toBeCloseTo(90 / 1440, 3)

        // three that overlap: a third of the column each, and no one covering another
        expect(first.width).toBeCloseTo(1 / 3, 2)
        expect(intersects(first.box, second.box)).toBe(false)
        expect(intersects(first.box, hour.box)).toBe(false)
        expect(intersects(second.box, hour.box)).toBe(false)

        // noon is the middle of the day, whichever way the clocks went
        const noon = await place(page, 'Noon', '2026-11-01', 'grid')

        expect(noon.top).toBeCloseTo(0.5, 3)

        // 09:30 UTC is 01:30 PST, the second time round
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '1:30 AM')

        const line = await nowLine(page, 'grid').boundingBox()
        const col = await column(page, '2026-11-01', 'grid').boundingBox()

        expect((line.y + line.height / 2 - col.y) / col.height).toBeCloseTo(90 / 1440, 3)
    })
})

test.describe('on a narrow screen', () => {
    test.use({ viewport: { width: 390, height: 844 } })

    test('the week scrolls sideways, in columns no narrower than 6rem, and the day fits', async ({ page }) => {
        await openGrid(page, 'view=week')

        const scroller = grid(page, 'grid')
        const widths = await scroller.evaluate((el) => ({ scroll: el.scrollWidth, client: el.clientWidth }))

        // 3.5rem of gutter and seven columns of 6rem
        const unit = await rem(page)

        expect(widths.scroll).toBeCloseTo((3.5 + 7 * 6) * unit, 0)
        expect(widths.scroll).toBeGreaterThan(widths.client + 100)

        const monday = await column(page, '2026-10-05', 'grid').boundingBox()

        expect(monday.width).toBeGreaterThanOrEqual(6 * unit - 0.5)

        await button(page, 'Day', 'grid').click()

        const day = await scroller.evaluate((el) => ({ scroll: el.scrollWidth, client: el.clientWidth }))

        expect(day.scroll).toBeLessThanOrEqual(day.client)

        const column1 = await column(page, '2026-10-09', 'grid').boundingBox()

        expect(column1.width).toBeCloseTo(day.client - 3.5 * unit, 0)
    })

    test('scrolling the week sideways keeps the hours in view and moves the days with their header', async ({ page }) => {
        await openGrid(page, 'view=week')

        const scroller = grid(page, 'grid')
        const hour = probe(page, 'grid').locator('[data-atom-calendar-grid-body] [data-atom-calendar-gutter]')
        const head = probe(page, 'grid').locator('[data-atom-calendar-column-head]').nth(3)

        const gutterBefore = await hour.boundingBox()
        const headBefore = await head.boundingBox()
        const body = await column(page, '2026-10-08', 'grid').boundingBox()

        await scroller.evaluate((el) => { el.scrollLeft = 200 })

        const gutterAfter = await hour.boundingBox()
        const headAfter = await head.boundingBox()
        const bodyAfter = await column(page, '2026-10-08', 'grid').boundingBox()

        // the gutter stays where it was; the header and its column move together
        expect(gutterAfter.x).toBeCloseTo(gutterBefore.x, 0)
        expect(headBefore.x - headAfter.x).toBeCloseTo(200, 0)
        expect(body.x - bodyAfter.x).toBeCloseTo(200, 0)
        expect(headAfter.x).toBeCloseTo(bodyAfter.x, 0)
    })

    test('the now line is exactly its column wide, in the day view and in the week', async ({ page }) => {
        for (const view of ['day', 'week']) {
            await openGrid(page, 'view=' + view)

            const col = await column(page, '2026-10-09', 'grid').boundingBox()
            const line = await nowLine(page, 'grid').boundingBox()
            const scroller = await grid(page, 'grid').boundingBox()

            expect(line.x, view).toBeCloseTo(col.x, 0)
            expect(line.x + line.width, view).toBeCloseTo(col.x + col.width, 0)

            // and in the day view that is the scroller's edge, so nothing is drawn past it
            if (view === 'day') expect(line.x + line.width).toBeLessThanOrEqual(scroller.x + scroller.width + 0.5)
        }
    })

    test('the toolbar still fits and the page does not scroll sideways', async ({ page }) => {
        await open(page, 'view=week')

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

        expect(overflow).toBeLessThanOrEqual(0)
        await expect(button(page, 'Week')).toBeVisible()
        await expect(button(page, 'Next')).toBeVisible()
        await expect(button(page, 'Today')).toBeVisible()
    })
})

for (const timezoneId of ['America/Los_Angeles', 'Pacific/Kiritimati']) {
    test.describe(`in a browser set to ${timezoneId}`, () => {
        test.use({ timezoneId })

        test('the week and its items are where the server put them, whatever the browser thinks', async ({ page }) => {
            await open(page, 'view=week')

            await expect(columns(page).first()).toHaveAttribute('data-date', '2026-10-05')
            await expect(column(page, '2026-10-09')).toHaveAttribute('aria-current', 'date')
            await expect(nowLine(page)).toHaveAttribute('data-time', '10:30 AM')

            const standup = await place(page, 'Team standup', '2026-10-09')

            expect(standup.top).toBeCloseTo(9.5 / 24, 3)
            await expect(chipOf(page, 'Team standup')).toContainText('9:30 AM – 10:00 AM')

            await button(page, 'Next').click()
            await expect(title(page)).toHaveText('12 – 18 October 2026')
        })

        test('jumping with the date picker lands on the day the user clicked', async ({ page }) => {
            await open(page, 'view=week')

            await probe(page).getByRole('button', { name: 'Choose date' }).click()
            await expect(probe(page).locator('.pika-lendar')).toBeVisible()
            await page.evaluate(() => {
                const picker = document.querySelector('[data-probe="calendar"] [data-atom-date-picker]')

                window.Alpine.$data(picker).pikaday.gotoDate(new Date(2026, 9, 22))
            })
            await probe(page).locator('.pika-button[data-pika-year="2026"][data-pika-month="9"][data-pika-day="22"]').click()

            await expect(title(page)).toHaveText('19 – 25 October 2026')
        })
    })
}

test.describe('an item is drawn at least half an hour tall, whatever an hour is', () => {
    const hours = [null, '2rem', '4rem']

    for (const hour of hours) {
        test(`at ${hour ?? 'the default'} an hour, short items a little apart and the last of the day never cover each other`, async ({ page }) => {
            await openGrid(page, 'scenario=tight&view=week&scroll=00:00')

            if (hour) await probe(page, 'grid').evaluate((el, value) => el.style.setProperty('--atom-calendar-hour', value), hour)

            const boxes = {}

            for (const name of ['A', 'B', 'C', 'D', 'E', 'F']) boxes[name] = await block(page, 'Tight ' + name, 'grid').boundingBox()

            // 26 minutes apart, 29 minutes apart, and an item drawn up from the end of the day over the one before it
            for (const [a, b] of [['A', 'B'], ['C', 'D'], ['E', 'F']]) {
                expect(intersects(boxes[a], boxes[b]), `Tight ${a} covers Tight ${b}`).toBe(false)
            }

            // the minimum is half an hour of this grid's hour
            const rows = probe(page, 'grid').locator('[data-atom-calendar-hour]')
            const hourHeight = (await rows.nth(1).boundingBox()).y - (await rows.nth(0).boundingBox()).y

            for (const name of ['A', 'B', 'C', 'D']) expect(boxes[name].height).toBeGreaterThanOrEqual(hourHeight / 2 - 0.5)

            // and the last one is moved up to stay inside its column
            const col = await column(page, '2026-10-09', 'grid').boundingBox()

            expect(boxes.F.y + boxes.F.height).toBeLessThanOrEqual(col.y + col.height + 0.5)
        })
    }
})

test.describe('stacking', () => {
    test('a host overlay with a low z-index is over the sticky header, not under it', async ({ page }) => {
        await openGrid(page, 'view=week')

        const hit = await page.evaluate(() => {
            const top = document.querySelector('[data-probe="grid"] [data-atom-calendar-grid-top]').getBoundingClientRect()
            const overlay = document.createElement('div')

            overlay.id = 'host-overlay'
            overlay.style.cssText = `position:fixed;z-index:3;left:${top.x}px;top:${top.y}px;width:${top.width}px;height:${top.height}px;background:red`
            document.body.appendChild(overlay)

            return document.elementFromPoint(top.x + 100, top.y + 10)?.id
        })

        expect(hit).toBe('host-overlay')
    })
})

test.describe('today after midnight', () => {
    test('moves to the next day on the clock alone: the column, the header and the month view', async ({ page }) => {
        // 23:59:30 in Kuala Lumpur
        await page.clock.install({ time: new Date('2026-10-09T15:59:30Z') })
        await openGrid(page, 'now=2026-10-09T15:59:30Z')

        const head = (date) => probe(page, 'grid').locator(`[data-atom-calendar-column-head][data-date="${date}"] span`).last()

        await expect(column(page, '2026-10-09', 'grid')).toHaveAttribute('aria-current', 'date')
        await expect(head('2026-10-09')).toHaveClass(/bg-zinc-900/)

        await page.clock.runFor('01:00')

        await expect(column(page, '2026-10-10', 'grid')).toHaveAttribute('aria-current', 'date')
        await expect(column(page, '2026-10-09', 'grid')).not.toHaveAttribute('aria-current', 'date')
        await expect(probe(page, 'grid').locator('[data-atom-calendar-column][aria-current="date"]')).toHaveCount(1)
        await expect(head('2026-10-10')).toHaveClass(/bg-zinc-900/)
        await expect(head('2026-10-09')).not.toHaveClass(/bg-zinc-900/)

        await button(page, 'Month', 'grid').click()
        await expect(probe(page, 'grid').locator('[role="gridcell"][aria-current="date"]')).toHaveAttribute('data-date', '2026-10-10')
    })
})

test.describe('a minute passing', () => {
    test('moves the now line and nothing else in the grid', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-10-09T02:30:00Z') })
        await openGrid(page)
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '10:30 AM')

        await page.evaluate(() => {
            window.__mutations = []

            const note = (records) => {
                for (const record of records) {
                    const element = record.target.nodeType === 1 ? record.target : record.target.parentElement

                    if (!element.closest('[data-atom-calendar-now-line]')) window.__mutations.push(record.type + ' ' + (record.attributeName ?? '') + ' on ' + element.tagName + '.' + element.className)
                }
            }

            window.__observer = new MutationObserver(note)
            window.__observer.observe(document.querySelector('[data-probe="grid"] [data-atom-calendar-grid]'), { subtree: true, childList: true, attributes: true, characterData: true })
            window.__note = note
        })

        await page.clock.runFor('05:00')
        await expect(nowLine(page, 'grid')).toHaveAttribute('data-time', '10:35 AM')

        const mutations = await page.evaluate(() => {
            window.__note(window.__observer.takeRecords())

            return window.__mutations
        })

        expect(mutations).toEqual([])
    })
})

test.describe('a viewer in Kuala Lumpur, in a browser in Los Angeles', () => {
    test.use({ timezoneId: 'America/Los_Angeles' })

    test('an item at a time the browser\'s own clock skips that day is still where the server says', async ({ page }) => {
        await openGrid(page, 'scenario=kl-gap&date=2026-03-08&view=day&scroll=00:00')

        const gap = await place(page, 'Gap item', '2026-03-08', 'grid')

        // 02:30 on the wall clock of the viewer, whatever the browser's does that morning
        expect(gap.top).toBeCloseTo(150 / 1440, 4)
        expect(gap.height).toBeCloseTo(60 / 1440, 4)
        await expect(chipOf(page, 'Gap item', 'grid')).toContainText('2:30 AM – 3:30 AM')
    })
})

test.describe('the week title in the viewer\'s language', () => {
    const cases = [
        ['en', '2026-10-09', '5 – 11 October 2026'],
        ['en', '2026-12-31', '28 Dec 2026 – 3 Jan 2027'],
        ['zh', '2026-10-09', '2026年10月5日 – 11日'],
        ['zh', '2026-10-30', '2026年10月26日 – 11月1日'],
        ['zh', '2026-12-31', '2026年12月28日 – 2027年1月3日'],
    ]

    for (const [locale, date, text] of cases) {
        test(`${locale}, week of ${date}: ${text}`, async ({ page }) => {
            await openGrid(page, `locale=${locale}&view=week&date=${date}`)

            await expect(title(page, 'grid')).toHaveText(text)
        })
    }
})
