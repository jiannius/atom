import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/calendar (tests/Fixtures/CalendarFixture.php) and the static
// page at /atom/e2e/calendar-static. Time is frozen at 2026-10-09 10:30 on every request, so the window
// for October is 2026-09-28 to 2026-11-08 under the default Monday start.
//
// The rig has no Tailwind: what these tests rely on is what atom.css owns (the grid, the chip rows, the
// popover position) plus the stand-in utilities in the fixture views. Colour and type are looked at in
// the screenshots, not asserted here.

const probe = (page, name = 'calendar') => page.locator(`[data-probe="${name}"]`)
const days = (page, name) => probe(page, name).locator('[data-atom-calendar-day]')
const day = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-day][data-date="${date}"]`)
const chip = (page, title, name) => probe(page, name).locator('[data-atom-calendar-chip]', { hasText: title })
const popover = (page, name) => probe(page, name).locator('[data-atom-calendar-popover]')
const title = (page, name) => probe(page, name).locator('[role="status"]')

const isUpdate = (response) => response.url().includes('livewire') && response.request().method() === 'POST'

// Count the Livewire round trips made from now on.
function countRequests (page) {
    const counter = { count: 0 }

    page.on('request', (request) => {
        if (request.url().includes('livewire') && request.method() === 'POST') counter.count++
    })

    return counter
}

async function bump (page, selector = '[data-bump]') {
    const renders = page.locator('[data-renders]')
    const before = Number(await renders.textContent())

    await page.locator(selector).click()
    await page.waitForResponse(isUpdate)
    await expect(renders).toHaveText(String(before + (selector === '[data-bump]' ? 1 : 0)))
}

async function open (page, path = '/atom/e2e/calendar') {
    await page.goto(path)
    await page.waitForFunction(() => window.Livewire && window.Alpine)
    await expect(page.locator('[data-atom-calendar-day]').first()).toBeVisible()
}

test.describe('bound to a Livewire property', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('lays out the six-week window around the anchor, names the month, and marks today', async ({ page }) => {
        await expect(days(page)).toHaveCount(42)
        await expect(days(page).first()).toHaveAttribute('data-date', '2026-09-28')
        await expect(days(page).last()).toHaveAttribute('data-date', '2026-11-08')
        await expect(title(page)).toHaveText('October 2026')
        await expect(probe(page).locator('[role="columnheader"]')).toHaveText(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])

        await expect(probe(page).locator('[aria-current="date"]')).toHaveCount(1)
        await expect(day(page, '2026-10-09')).toHaveAttribute('aria-current', 'date')
    })

    test('Alpine strips every x-cloak it was given, so none stays hidden for good', async ({ page }) => {
        await expect(probe(page).locator('[x-cloak]')).toHaveCount(0)
        await expect(probe(page).locator('[role="grid"]')).toBeVisible()
        await expect(probe(page).locator('[x-show="loading"]')).toBeHidden()
    })

    test('labels a day with its date and how many items it holds', async ({ page }) => {
        await expect(day(page, '2026-10-14')).toHaveAttribute('aria-label', 'Wednesday, 14 Oct 2026, 5 items')
        await expect(day(page, '2026-10-09')).toHaveAttribute('aria-label', 'Friday, 9 Oct 2026, 3 items')
        await expect(day(page, '2026-10-16')).toHaveAttribute('aria-label', 'Friday, 16 Oct 2026')
    })

    test('draws all-day items as bars across their days and timed ones as a time and a title', async ({ page }) => {
        // Launch week runs Mon 5 to Fri 9: one bar over five columns
        const bar = chip(page, 'Launch week')
        const cell = await day(page, '2026-10-05').boundingBox()
        const last = await day(page, '2026-10-09').boundingBox()
        const box = await bar.boundingBox()

        expect(box.x).toBeLessThan(cell.x + 12)
        expect(box.x + box.width).toBeGreaterThan(last.x + last.width - 12)

        // a timed item is a time then a title
        await expect(chip(page, 'Team standup')).toContainText('9:30 AM')
        await expect(chip(page, 'Team standup')).toHaveAttribute('data-atom-calendar-chip', 'dot')
        await expect(bar).toHaveAttribute('data-atom-calendar-chip', 'bar')
    })

    test('splits a bar at the week row and says it carries on', async ({ page }) => {
        const bars = probe(page).locator('[data-atom-calendar-chip="bar"]', { hasText: 'Conference' })

        await expect(bars).toHaveCount(2)
        await expect(bars.first()).toHaveAttribute('data-after', '')
        await expect(bars.first()).not.toHaveAttribute('data-before', '')
        await expect(bars.last()).toHaveAttribute('data-before', '')
        await expect(bars.last()).not.toHaveAttribute('data-after', '')
    })

    test('a bar that carries on into another week shows its focus outline, notch or not', async ({ page }) => {
        // the rig has no Tailwind: the outline the chip's focus-visible utilities would draw, as any element would get it
        await page.addStyleTag({ content: ':focus-visible { outline: 2px solid rgb(255, 0, 0); outline-offset: 1px }' })

        const redPixelsAround = async (locator) => {
            await locator.scrollIntoViewIfNeeded()

            const box = await locator.boundingBox()
            const png = await page.screenshot({
                clip: { x: Math.max(box.x - 6, 0), y: Math.max(box.y - 6, 0), width: box.width + 12, height: box.height + 12 },
            })

            return page.evaluate(async (base64) => {
                const image = new Image()

                image.src = 'data:image/png;base64,' + base64
                await image.decode()

                const canvas = document.createElement('canvas')

                canvas.width = image.width
                canvas.height = image.height

                const context = canvas.getContext('2d')

                context.drawImage(image, 0, 0)

                const { data } = context.getImageData(0, 0, canvas.width, canvas.height)
                let red = 0

                for (let i = 0; i < data.length; i += 4) {
                    if (data[i] > 200 && data[i + 1] < 60 && data[i + 2] < 60) red++
                }

                return red
            }, png.toString('base64'))
        }

        // the measurement itself: a bar inside one week, with no notch, shows it
        const plain = chip(page, 'Launch week')

        await plain.focus()
        expect(await redPixelsAround(plain), 'a plain bar, focused').toBeGreaterThan(40)
        await plain.blur()

        const bars = probe(page).locator('[data-atom-calendar-chip="bar"]', { hasText: 'Conference' })

        for (const [index, edge] of [[0, 'data-after'], [1, 'data-before']]) {
            const bar = bars.nth(index)

            await expect(bar).toHaveAttribute(edge, '')
            expect(await redPixelsAround(bar), `${edge} bar, not focused`).toBe(0)

            await bar.focus()

            await expect(bar).toBeFocused()
            expect(await redPixelsAround(bar), `${edge} bar, focused`).toBeGreaterThan(40)

            await bar.blur()
        }
    })

    test('shows three rows a day, then "+N more" with the exact number left over', async ({ page }) => {
        const wednesday = await day(page, '2026-10-14').boundingBox()
        const inside = async (locator) => {
            const box = await locator.boundingBox()

            return box.x >= wednesday.x && box.x < wednesday.x + wednesday.width
        }

        for (const name of ['Dentist', 'Design review', 'Lunch with Sam']) {
            await expect(chip(page, name)).toBeVisible()
            expect(await inside(chip(page, name))).toBe(true)
        }

        await expect(chip(page, 'Call the bank')).toHaveCount(0)
        await expect(chip(page, 'School pickup')).toHaveCount(0)
        await expect(probe(page).locator('[data-atom-calendar-more]')).toHaveText('+2 more')
    })

    test('opens a day list from "+N more", with every item that day, and closes it with Escape', async ({ page }) => {
        const requests = countRequests(page)

        await probe(page).locator('[data-atom-calendar-more]').click()

        await expect(popover(page)).toBeVisible()
        await expect(popover(page)).toHaveAttribute('role', 'dialog')
        await expect(popover(page)).toHaveAttribute('aria-label', 'Wednesday, 14 Oct 2026')
        await expect(popover(page).locator('[data-atom-calendar-chip]')).toHaveCount(5)
        await expect(popover(page).locator('[data-atom-calendar-chip]').nth(0)).toContainText('Dentist')
        await expect(popover(page).locator('[data-atom-calendar-chip]').nth(4)).toContainText('School pickup')
        await expect(popover(page)).toContainText('WED'.toLowerCase(), { ignoreCase: true })
        await expect(popover(page)).toContainText('14')

        await page.keyboard.press('Escape')
        await expect(popover(page)).toBeHidden()

        expect(requests.count).toBe(0)
    })

    test('closes the day list with the close button, a click outside, and a second click on its trigger', async ({ page }) => {
        const more = probe(page).locator('[data-atom-calendar-more]')

        await more.click()
        await expect(popover(page)).toBeVisible()
        await popover(page).getByRole('button', { name: 'Close' }).click()
        await expect(popover(page)).toBeHidden()

        await more.click()
        await expect(popover(page)).toBeVisible()
        await page.locator('[data-renders]').click()
        await expect(popover(page)).toBeHidden()

        // the same trigger again toggles it shut instead of light-dismissing and reopening
        await more.click()
        await expect(popover(page)).toBeVisible()
        await more.click()
        await expect(popover(page)).toBeHidden()
    })

    test('opens a day list from a click on a day that holds items, and not from an empty one', async ({ page }) => {
        await day(page, '2026-10-16').click()
        await expect(popover(page)).toBeHidden()

        await day(page, '2026-10-09').click({ position: { x: 20, y: 90 } })
        await expect(popover(page)).toBeVisible()
        await expect(popover(page).locator('[data-atom-calendar-chip]')).toHaveCount(3)
        await expect(popover(page).locator('[data-atom-calendar-chip]').first()).toContainText('Launch week')
    })

    test('puts the day list at its anchor after the page has been scrolled', async ({ page }) => {
        // a top-layer box that is `absolute` resolves against the document origin, not the viewport, and
        // would miss by exactly the scroll distance
        await page.evaluate(() => document.body.insertAdjacentHTML('afterbegin', '<div style="height:1600px"></div>'))
        await probe(page).locator('[data-atom-calendar-more]').scrollIntoViewIfNeeded()
        await page.evaluate(() => window.scrollBy(0, 40))

        const scrolled = await page.evaluate(() => window.scrollY)
        expect(scrolled).toBeGreaterThan(1000)

        await probe(page).locator('[data-atom-calendar-more]').click()
        await expect(popover(page)).toBeVisible()

        const cell = await day(page, '2026-10-14').boundingBox()
        const panel = await popover(page).boundingBox()

        // below the cell when there is room for it, above when there is not; either way touching it
        const below = Math.abs(panel.y - (cell.y + cell.height))
        const above = Math.abs(panel.y + panel.height - cell.y)

        expect(Math.min(below, above)).toBeLessThan(8)
        expect(panel.y).toBeGreaterThanOrEqual(0)
    })

    test('tells the host which item was chosen, and follows a link instead', async ({ page }) => {
        await page.evaluate(() => {
            window.__clicks = []
            document.querySelector('[data-probe="calendar"]').addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail))
        })

        await chip(page, 'Team standup').click()
        await expect.poll(() => page.evaluate(() => window.__clicks)).toEqual([{ id: 1 }])

        await expect(chip(page, 'Team standup')).toHaveAttribute('role', 'button')
        await expect(chip(page, 'Team standup')).not.toHaveAttribute('href', /.*/)

        const link = chip(page, 'Open the docs')
        await expect(link).toHaveAttribute('href', '/atom/docs')
        await expect(link).not.toHaveAttribute('role', /.*/)

        // an item that is a link does not also tell the host it was clicked
        await page.evaluate(() => document.querySelector('[data-probe="calendar"]').addEventListener('click', (event) => event.target.closest('a[href]') && event.preventDefault()))
        await link.click()
        await expect.poll(() => page.evaluate(() => window.__clicks)).toEqual([{ id: 1 }])
    })

    test('answers Enter and Space on an item that is not a link', async ({ page }) => {
        await page.evaluate(() => {
            window.__clicks = []
            document.querySelector('[data-probe="calendar"]').addEventListener('calendar-item-click', (event) => window.__clicks.push(event.detail.id))
        })

        const item = chip(page, 'Team standup')

        await item.focus()
        await page.keyboard.press('Enter')
        await page.keyboard.press('Space')

        await expect.poll(() => page.evaluate(() => window.__clicks)).toEqual([1, 1])
    })

    test('never gives a javascript: link an href', async ({ page }) => {
        const unsafe = chip(page, 'Unsafe link')

        await expect(unsafe).toBeVisible()
        await expect(unsafe).not.toHaveAttribute('href', /.*/)
        await expect(unsafe).toHaveAttribute('role', 'button')

        expect(await page.evaluate(() => window.__hacked)).toBeUndefined()
    })

    test('checks a link again in the browser, whatever the payload says', async ({ page }) => {
        // the server drops an unsafe href; this is the second gate, at the sink
        await page.evaluate(() => {
            const root = document.querySelector('[data-probe="calendar"]')
            const payload = JSON.parse(root.getAttribute('data-atom-calendar-payload'))

            payload.items.find((item) => item.title === 'Team standup').href = 'javascript:window.__hacked = 1'
            payload.items.find((item) => item.title === 'Dentist').href = '/safe'
            root.setAttribute('data-atom-calendar-payload', JSON.stringify(payload))
        })

        await expect(chip(page, 'Dentist')).toHaveAttribute('href', '/safe')
        await expect(chip(page, 'Team standup')).not.toHaveAttribute('href', /.*/)
        await expect(chip(page, 'Team standup')).toHaveAttribute('role', 'button')
    })

    test('paints a hex colour from its own variable, and a danger item with a warning icon', async ({ page }) => {
        const dot = chip(page, 'Hex colour').locator('[data-atom-calendar-dot]')

        await expect(dot).toHaveAttribute('data-atom-calendar-hex', '')
        expect(await dot.evaluate((el) => el.style.getPropertyValue('--atom-calendar-hue'))).toBe('#ff8800')
        // the dot is the colour moved to show on a light ground (3:1, see calendar/colors.js): the orange is darkened, and the dark theme's value is kept beside it
        expect(await dot.evaluate((el) => el.style.getPropertyValue('--atom-calendar-edge'))).toBe('#cc6d00')
        expect(await dot.evaluate((el) => el.style.getPropertyValue('--atom-calendar-edge-dark'))).toBe('#ff8800')
        expect(await dot.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(204, 109, 0)')

        const danger = chip(page, 'Invoice overdue')

        await expect(danger.locator('svg')).toHaveCount(1)
        await expect(danger.getByText('Overdue', { exact: true })).toHaveCount(1)
    })

    test('an item with no colour is zinc, not blue', async ({ page }) => {
        // Open the docs has no colour: its bar takes the zinc palette entry the server printed
        const bar = chip(page, 'Open the docs')

        await expect(bar).toHaveClass(/bg-zinc-100/)
        await expect(bar).toHaveClass(/border-zinc-500/)
        await expect(bar).not.toHaveClass(/sky/)
    })

    test('clones the icon the server rendered for an item', async ({ page }) => {
        await expect(chip(page, 'Open the docs').locator('svg')).toHaveCount(1)
        await expect(chip(page, 'Team standup').locator('svg')).toHaveCount(0)
    })

    test('has a switcher for the views it is given, with the one on screen pressed', async ({ page }) => {
        await expect(probe(page).getByRole('button', { name: 'Month', exact: true })).toHaveAttribute('aria-pressed', 'true')
        await expect(probe(page).getByRole('button', { name: 'Week', exact: true })).toHaveAttribute('aria-pressed', 'false')
        await expect(probe(page).getByRole('button', { name: 'Day', exact: true })).toHaveAttribute('aria-pressed', 'false')
    })
})

test.describe('navigating', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('next and previous go to the neighbouring month in one request each', async ({ page }) => {
        const requests = countRequests(page)

        await probe(page).getByRole('button', { name: 'Next' }).click()
        await page.waitForResponse(isUpdate)

        await expect(title(page)).toHaveText('November 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-11-09')
        await expect(days(page).first()).toHaveAttribute('data-date', '2026-10-26')
        await expect(chip(page, 'Month end review')).toBeVisible()
        await expect(chip(page, 'Dentist')).toHaveCount(0)
        expect(requests.count).toBe(1)

        await probe(page).getByRole('button', { name: 'Previous' }).click()
        await page.waitForResponse(isUpdate)

        await expect(title(page)).toHaveText('October 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-10-09')
        await expect(chip(page, 'Dentist')).toBeVisible()
        expect(requests.count).toBe(2)
    })

    test('every icon template holds its own glyph after the window changes, and a danger chip shows the warning', async ({ page }) => {
        const template = (name) => probe(page).locator(`template[data-atom-calendar-icon="${name}"]`)
        const html = (name) => template(name).evaluate((el) => el.innerHTML.trim())

        // what the server printed on first load is the reference for each glyph
        const reference = { calendar: await html('calendar'), warning: await html('warning'), loading: await html('loading') }

        expect(reference.calendar).toContain('<svg')
        expect(reference.warning).toContain('<svg')
        expect(reference.loading).toContain('<svg')
        expect(reference.warning).not.toBe(reference.calendar)
        expect(reference.loading).not.toBe(reference.calendar)

        const check = async (names) => {
            await expect(probe(page).locator('template[data-atom-calendar-icon]')).toHaveCount(names.length)

            for (const name of names) {
                expect(await html(name), name).toBe(reference[name])
            }
        }

        // the warning, and the spinner of an item waiting for the host, are always there
        await check(['calendar', 'warning', 'loading'])

        await probe(page).getByRole('button', { name: 'Next' }).click()
        await expect(title(page)).toHaveText('November 2026')
        await check(['warning', 'loading'])

        const danger = chip(page, 'Contract overdue').locator('[data-atom-icon]')
        await expect(danger).toHaveCount(1)
        expect(await danger.evaluate((el) => el.outerHTML.trim())).toBe(reference.warning)

        await probe(page).getByRole('button', { name: 'Previous' }).click()
        await expect(title(page)).toHaveText('October 2026')
        await check(['calendar', 'warning', 'loading'])

        const docs = chip(page, 'Open the docs').locator('[data-atom-icon]')
        await expect(docs).toHaveCount(1)
        expect(await docs.evaluate((el) => el.outerHTML.trim())).toBe(reference.calendar)
    })

    test('Today brings it back from another month, and does nothing more when already there', async ({ page }) => {
        const requests = countRequests(page)

        await probe(page).getByRole('button', { name: 'Next' }).click()
        await expect(title(page)).toHaveText('November 2026')

        await probe(page).getByRole('button', { name: 'Today' }).click()
        await expect(title(page)).toHaveText('October 2026')
        await expect(day(page, '2026-10-09')).toHaveAttribute('aria-current', 'date')
        expect(requests.count).toBe(2)

        await probe(page).getByRole('button', { name: 'Today' }).click()
        await page.waitForTimeout(300)
        expect(requests.count).toBe(2)
    })

    test('moving to a date in the month on screen needs no request, and says so to the host silently', async ({ page }) => {
        const requests = countRequests(page)

        await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).navigate('2026-10-20'))
        await page.waitForTimeout(300)

        expect(requests.count).toBe(0)
        expect(await page.evaluate(() => window.Alpine.evaluate(document.querySelector('[data-probe="calendar"]'), '$wire.date'))).toBe('2026-10-20')
        await expect(title(page)).toHaveText('October 2026')
    })

    test('jumping with the date picker in the title goes to that month', async ({ page }) => {
        const requests = countRequests(page)

        await probe(page).getByRole('button', { name: 'Choose date' }).click()

        const calendar = probe(page).locator('.pika-lendar')
        await expect(calendar).toBeVisible()

        // 2026-12-15: pikaday counts months from 0
        await page.evaluate(() => {
            const root = document.querySelector('[data-probe="calendar"]')
            const picker = root.querySelector('[data-atom-date-picker]')

            window.Alpine.$data(picker).pikaday.gotoDate(new Date(2026, 11, 15))
        })

        await probe(page).locator('.pika-button[data-pika-year="2026"][data-pika-month="11"][data-pika-day="15"]').click()
        await page.waitForResponse(isUpdate)

        await expect(title(page)).toHaveText('December 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-12-15')
        expect(requests.count).toBe(1)
    })

    test('two quick clicks on Next reach the month after next, not the next month twice', async ({ page }) => {
        await page.route(/livewire.*update/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500))
            await route.continue()
        })

        const next = probe(page).getByRole('button', { name: 'Next' })

        await next.click()
        await next.click()

        await expect(title(page)).toHaveText('December 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-12-09')
        await expect(probe(page).locator('[x-show="loading"]')).toBeHidden()
    })

    test('Next then Previous before the answer arrives ends on the month it started on', async ({ page }) => {
        await page.route(/livewire.*update/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500))
            await route.continue()
        })

        await probe(page).getByRole('button', { name: 'Next' }).click()
        await probe(page).getByRole('button', { name: 'Previous' }).click()

        await expect(probe(page).locator('[x-show="loading"]')).toBeHidden()
        await expect(title(page)).toHaveText('October 2026')
        await expect(page.locator('span[data-date]')).toHaveText('2026-10-09')
        expect(await page.evaluate(() => window.Alpine.evaluate(document.querySelector('[data-probe="calendar"]'), '$wire.date'))).toBe('2026-10-09')
    })

    test('the date picker follows Today after a day was picked inside the month', async ({ page }) => {
        const requests = countRequests(page)

        await probe(page).getByRole('button', { name: 'Choose date' }).click()
        await probe(page).locator('.pika-button[data-pika-year="2026"][data-pika-month="9"][data-pika-day="20"]').click()
        await expect.poll(() => page.evaluate(() => window.Alpine.evaluate(document.querySelector('[data-probe="calendar"]'), '$wire.date'))).toBe('2026-10-20')

        await probe(page).getByRole('button', { name: 'Today' }).click()
        await expect.poll(() => page.evaluate(() => window.Alpine.evaluate(document.querySelector('[data-probe="calendar"]'), '$wire.date'))).toBe('2026-10-09')

        await probe(page).getByRole('button', { name: 'Choose date' }).click()
        await expect(probe(page).locator('.is-selected .pika-button')).toHaveAttribute('data-pika-day', '9')
        expect(requests.count).toBe(0)
    })

    test('the date picker offers the day on screen when it opens', async ({ page }) => {
        await probe(page).getByRole('button', { name: 'Choose date' }).click()

        await expect(probe(page).locator('.pika-button.pika-day').first()).toBeVisible()
        await expect(probe(page).locator('.is-selected .pika-button')).toHaveAttribute('data-pika-day', '9')
        await expect(probe(page).locator('.pika-select-month')).toHaveValue('9')
    })

    test('the spinner shows while the next window is on its way and is gone when it lands', async ({ page }) => {
        await page.route(/livewire.*update/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 600))
            await route.continue()
        })

        const spinner = probe(page).locator('[x-show="loading"]')

        await expect(spinner).toBeHidden()
        await probe(page).getByRole('button', { name: 'Next' }).click()
        await expect(spinner).toBeVisible()
        await expect(title(page)).toHaveText('November 2026')
        await expect(spinner).toBeHidden()
    })

    test('an open day list closes when the window changes', async ({ page }) => {
        await probe(page).locator('[data-atom-calendar-more]').click()
        await expect(popover(page)).toBeVisible()

        // not through the page's own button: a click elsewhere would dismiss the list by itself
        await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).next())
        await expect(title(page)).toHaveText('November 2026')
        await expect(popover(page)).toBeHidden()
    })
})

test.describe('a Livewire re-render', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('a render that only moves the clock leaves the payload alone, so nothing is laid out again', async ({ page }) => {
        const root = probe(page)

        await page.evaluate(() => {
            const el = document.querySelector('[data-probe="calendar"]')

            window.__payloadWrites = 0
            window.__nowBefore = el.getAttribute('data-atom-calendar-now')
            new MutationObserver((records) => { window.__payloadWrites += records.length })
                .observe(el, { attributes: true, attributeFilter: ['data-atom-calendar-payload'] })
        })

        await bump(page)
        await bump(page)
        await page.waitForTimeout(200)

        expect(await page.evaluate(() => window.__payloadWrites)).toBe(0)
        // the clock did move: it is what the now-indicator will read
        expect(await root.getAttribute('data-atom-calendar-now')).not.toBe(await page.evaluate(() => window.__nowBefore))
    })

    test('leaves the chips and the cells where they were, the very same nodes', async ({ page }) => {
        await page.evaluate(() => {
            const root = document.querySelector('[data-probe="calendar"]')

            window.__marked = {
                body: root.querySelector('[data-atom-calendar-body]'),
                cell: root.querySelector('[data-atom-calendar-day][data-date="2026-10-14"]'),
                chip: [...root.querySelectorAll('[data-atom-calendar-chip]')].find((el) => el.textContent.includes('Team standup')),
            }
        })

        await bump(page)

        expect(await page.evaluate(() => {
            const root = document.querySelector('[data-probe="calendar"]')

            return [
                window.__marked.body === root.querySelector('[data-atom-calendar-body]'),
                window.__marked.cell === root.querySelector('[data-atom-calendar-day][data-date="2026-10-14"]'),
                window.__marked.chip === [...root.querySelectorAll('[data-atom-calendar-chip]')].find((el) => el.textContent.includes('Team standup')),
            ]
        })).toEqual([true, true, true])

        await expect(days(page)).toHaveCount(42)
    })

    test('updates what the host put in the toolbar slot, which is not in wire:ignore', async ({ page }) => {
        await expect(page.locator('[data-host-slot]')).toHaveText('Slot renders: 0')

        await bump(page)

        await expect(page.locator('[data-host-slot]')).toHaveText('Slot renders: 1')
    })

    test('takes a new payload: an added item appears, and nothing else is rebuilt', async ({ page }) => {
        await page.evaluate(() => {
            window.__dentist = [...document.querySelectorAll('[data-atom-calendar-chip]')].find((el) => el.textContent.includes('Dentist'))
            window.__before = document.querySelector('[data-probe="calendar"]').getAttribute('data-atom-calendar-payload')
        })

        await expect(chip(page, 'Added later')).toHaveCount(0)

        await bump(page, '[data-toggle-extra]')

        await expect(chip(page, 'Added later')).toBeVisible()

        expect(await page.evaluate(() => ({
            same: window.__dentist === [...document.querySelectorAll('[data-atom-calendar-chip]')].find((el) => el.textContent.includes('Dentist')),
            changed: document.querySelector('[data-probe="calendar"]').getAttribute('data-atom-calendar-payload') !== window.__before,
        }))).toEqual({ same: true, changed: true })

        // and goes away again
        await bump(page, '[data-toggle-extra]')
        await expect(chip(page, 'Added later')).toHaveCount(0)
    })

    test('keeps an open day list open while the payload around it changes', async ({ page }) => {
        await day(page, '2026-10-09').click({ position: { x: 20, y: 90 } })
        await expect(popover(page).locator('[data-atom-calendar-chip]')).toHaveCount(3)

        // the day list is for 9 October, which a re-render does not move. Called from the page, not clicked:
        // a click outside the list would dismiss it
        await Promise.all([
            page.waitForResponse(isUpdate),
            page.evaluate(() => window.Alpine.evaluate(document.querySelector('[data-probe="calendar"]'), '$wire.toggleExtra()')),
        ])

        await expect(popover(page)).toBeVisible()
        await expect(popover(page).locator('[data-atom-calendar-chip]')).toHaveCount(4)
    })

    test('does not stack a second observer or calendar when init runs again on the node', async ({ page }) => {
        // Alpine can re-enter a node that is already set up; the earlier run must be put away first
        const reads = await page.evaluate(async () => {
            const root = document.querySelector('[data-probe="calendar"]')
            const data = window.Alpine.$data(root)
            const read = data.read.bind(data)
            let count = 0

            data.read = () => { count++; return read() }
            data.init()

            root.setAttribute('data-atom-calendar-payload', root.getAttribute('data-atom-calendar-payload'))
            await new Promise((resolve) => setTimeout(resolve, 100))

            return count
        })

        // init() reads once itself; the one observer that is left reads once more
        expect(reads).toBe(2)

        await expect(days(page)).toHaveCount(42)
        await expect(probe(page).locator('[data-atom-calendar-more]')).toHaveCount(1)
        await expect(chip(page, 'Team standup')).toHaveCount(1)
    })

    test('survives Alpine tearing the tree down and building it again', async ({ page }) => {
        await page.evaluate(() => {
            const root = document.querySelector('[data-probe="calendar"]')

            window.Alpine.destroyTree(root)
            window.Alpine.initTree(root)
        })

        await expect(days(page)).toHaveCount(42)
        await expect(chip(page, 'Team standup')).toHaveCount(1)
    })

    test('reads a payload it cannot parse as "keep what is on screen"', async ({ page }) => {
        const messages = []

        page.on('console', (message) => message.type() === 'warning' && messages.push(message.text()))

        await page.evaluate(() => document.querySelector('[data-probe="calendar"]').setAttribute('data-atom-calendar-payload', '{nope'))
        await expect.poll(() => messages.length).toBeGreaterThan(0)

        await expect(days(page)).toHaveCount(42)
        await expect(chip(page, 'Team standup')).toBeVisible()
    })
})

test.describe('static, with nothing bound', () => {
    test.beforeEach(async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')
    })

    test('shows the month it is given, without navigation controls', async ({ page }) => {
        await expect(days(page, 'static')).toHaveCount(42)
        await expect(title(page, 'static')).toHaveText('October 2026')

        await expect(probe(page, 'static').getByRole('button', { name: 'Next' })).toHaveCount(0)
        await expect(probe(page, 'static').getByRole('button', { name: 'Previous' })).toHaveCount(0)
        await expect(probe(page, 'static').getByRole('button', { name: 'Today' })).toHaveCount(0)
        await expect(probe(page, 'static').getByRole('button', { name: 'Choose date' })).toHaveCount(0)

        // one view, so nothing to switch between
        await expect(probe(page, 'static').getByRole('button', { name: 'Month', exact: true })).toBeHidden()
    })

    test('lays out its items and opens a day list', async ({ page }) => {
        await expect(chip(page, 'Static item', 'static')).toBeVisible()
        await expect(probe(page, 'static').locator('[data-atom-calendar-more]')).toHaveText('+2 more')

        await probe(page, 'static').locator('[data-atom-calendar-more]').click()
        await expect(popover(page, 'static')).toBeVisible()
        await expect(popover(page, 'static').locator('[data-atom-calendar-chip]')).toHaveCount(5)
    })

    test('bubbles an item click to the page around it', async ({ page }) => {
        await chip(page, 'Static item', 'static').click()

        await expect(page.locator('[data-clicked]')).toHaveText('a')
    })

    test('Escape closes the day list inside a modal and leaves the modal open', async ({ page }) => {
        await page.locator('[data-open-modal]').click()

        const dialog = page.locator('[data-modal]')

        await expect(dialog).toHaveAttribute('open', '')
        await probe(page, 'modal').locator('[data-atom-calendar-more]').click()
        await expect(popover(page, 'modal')).toBeVisible()

        await page.keyboard.press('Escape')

        await expect(popover(page, 'modal')).toBeHidden()
        await page.waitForTimeout(250)
        await expect(dialog).toHaveAttribute('open', '')

        // with no day list open, Escape is the modal's again
        await page.keyboard.press('Escape')
        await expect(dialog).not.toHaveAttribute('open', '')
    })

    test('Alpine strips every x-cloak it was given there too', async ({ page }) => {
        await expect(probe(page, 'static').locator('[x-cloak]')).toHaveCount(0)
        await expect(probe(page, 'static').locator('[role="grid"]')).toBeVisible()
    })

    test('fills a flex parent instead of collapsing to nothing', async ({ page }) => {
        const root = probe(page, 'flex')

        await expect(root.locator('[data-atom-calendar-day]').first()).toBeVisible()

        const box = await root.boundingBox()
        const parent = await page.locator('[data-flex-parent]').boundingBox()

        expect(box.width).toBeGreaterThan(parent.width - 2)
        expect((await root.locator('[data-atom-calendar-day]').first().boundingBox()).width).toBeGreaterThan(100)
    })

    // A host's Tailwind utilities land in @layer utilities. The rig has no Tailwind, so stand one in: an
    // unlayered atom rule would beat it, a layered one must not. In px, because the rig's root font size is
    // 14px, so Tailwind's 24rem would come out at 336.
    test('leaves the width to a host utility class', async ({ page }) => {
        await page.addStyleTag({ content: '@layer base, utilities; @layer utilities { .w-96 { width: 384px } }' })

        const root = probe(page, 'sized')

        await expect(root.locator('[data-atom-calendar-day]').first()).toBeVisible()
        expect((await root.boundingBox()).width).toBe(384)
    })
})

for (const timezoneId of ['America/Los_Angeles', 'Pacific/Kiritimati']) {
    test.describe(`in a browser set to ${timezoneId}`, () => {
        test.use({ timezoneId })

        test('puts every item on the day and at the time the server wrote, whatever the browser thinks', async ({ page }) => {
            await open(page)

            await expect(days(page).first()).toHaveAttribute('data-date', '2026-09-28')
            await expect(days(page).last()).toHaveAttribute('data-date', '2026-11-08')
            await expect(day(page, '2026-10-09')).toHaveAttribute('aria-current', 'date')
            await expect(chip(page, 'Team standup')).toContainText('9:30 AM')
            await expect(chip(page, 'Hex colour')).toContainText('2:00 PM')

            // the bar over the date line stays on the days it was given
            const bars = probe(page).locator('[data-atom-calendar-chip="bar"]', { hasText: 'Launch week' })
            await expect(bars).toHaveCount(1)

            const first = await day(page, '2026-10-05').boundingBox()
            const box = await bars.boundingBox()
            expect(Math.abs(box.x - first.x)).toBeLessThan(12)

            await probe(page).getByRole('button', { name: 'Next' }).click()
            await expect(title(page)).toHaveText('November 2026')
            await expect(chip(page, 'Month end review')).toBeVisible()
        })
    })
}
