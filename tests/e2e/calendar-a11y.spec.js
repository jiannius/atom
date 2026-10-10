import { test, expect } from '@playwright/test'

// The keyboard and the accessibility tree of <atom:calendar>: the month grid's one tab stop and its keys, the focus across a change of window
// and in and out of the day list, what a header and a block are called, and ids that two calendars on a page do not share.
//
// Three pages: the Livewire fixture (/atom/e2e/calendar, a bound anchor date, frozen at 2026-10-09 10:30, so October's window is 2026-09-28 to
// 2026-11-08), the static one (/atom/e2e/calendar-static) and the grid one (/atom/e2e/calendar-grid, `?scenario=names`). Names are read the way
// a screen reader reads them: through getByRole, which computes the accessible name.

const probe = (page, name = 'calendar') => page.locator(`[data-probe="${name}"]`)
const cells = (page, name) => probe(page, name).locator('[data-atom-calendar-day]')
const cell = (page, date, name) => probe(page, name).locator(`[data-atom-calendar-day][data-date="${date}"]`)
const popover = (page, name) => probe(page, name).locator('[data-atom-calendar-popover]')
const title = (page, name) => probe(page, name).locator('[role="status"]')

const isUpdate = (response) => response.url().includes('livewire') && response.request().method() === 'POST'

function countRequests (page) {
    const counter = { count: 0 }

    page.on('request', (request) => {
        if (request.url().includes('livewire') && request.method() === 'POST') counter.count++
    })

    return counter
}

async function open (page, path = '/atom/e2e/calendar') {
    await page.goto(path)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
    await expect(page.locator('[data-atom-calendar] [x-cloak]')).toHaveCount(0)
}

// the date of the day the focus is on, or null when it is on anything else
const focused = (page) => page.evaluate(() => (document.activeElement?.matches('[data-atom-calendar-day]') ? document.activeElement.dataset.date : null))

// the days of a calendar the Tab key stops on
const tabStops = (page, name) => cells(page, name).evaluateAll((els) => els.filter((el) => el.tabIndex === 0).map((el) => el.dataset.date))

test.describe('the month grid is one tab stop, and the keys walk it', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('exactly one day is a tab stop: today, and the rest are reached by the keys', async ({ page }) => {
        await expect(cells(page)).toHaveCount(42)
        expect(await tabStops(page)).toEqual(['2026-10-09'])
        await expect(cells(page).and(page.locator('[tabindex="-1"]'))).toHaveCount(41)
    })

    test('the tab stop moves with the focus, and there is still exactly one', async ({ page }) => {
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('ArrowRight')

        expect(await focused(page)).toBe('2026-10-10')
        expect(await tabStops(page)).toEqual(['2026-10-10'])

        // a click on a day is a focus on it as well
        await cell(page, '2026-10-16').click()
        expect(await tabStops(page)).toEqual(['2026-10-16'])
    })

    test('Tab from the toolbar reaches the grid on its one stop, and no other day', async ({ page }) => {
        await page.getByRole('button', { name: 'Next', exact: true }).focus()

        // the toolbar's own controls and the chips of the weeks before it come first: the one day the focus lands on is the tab stop
        const landed = new Set()

        for (let i = 0; i < 14; i++) {
            await page.keyboard.press('Tab')

            const date = await focused(page)

            if (date) landed.add(date)
        }

        expect([...landed]).toEqual(['2026-10-09'])
    })

    for (const [name, from, key, to] of [
        ['ArrowRight is the next day', '2026-10-14', 'ArrowRight', '2026-10-15'],
        ['ArrowLeft is the day before', '2026-10-14', 'ArrowLeft', '2026-10-13'],
        ['ArrowDown is the same day next week', '2026-10-14', 'ArrowDown', '2026-10-21'],
        ['ArrowUp is the same day last week', '2026-10-14', 'ArrowUp', '2026-10-07'],
        ['Home is the first day of the week (Monday)', '2026-10-14', 'Home', '2026-10-12'],
        ['End is the last day of the week (Sunday)', '2026-10-14', 'End', '2026-10-18'],
        ['ArrowRight walks onto a day of the next month without changing the month', '2026-10-31', 'ArrowRight', '2026-11-01'],
    ]) {
        test(name, async ({ page }) => {
            const requests = countRequests(page)

            await cell(page, from).focus()
            await page.keyboard.press(key)

            expect(await focused(page)).toBe(to)
            expect(await tabStops(page)).toEqual([to])
            await expect(title(page)).toHaveText('October 2026')
            expect(requests.count).toBe(0)
        })
    }

    test('modified keys are the browser\'s own: Ctrl+ArrowRight does not move the focus', async ({ page }) => {
        await cell(page, '2026-10-14').focus()
        await page.keyboard.press('Control+ArrowRight')

        expect(await focused(page)).toBe('2026-10-14')
    })

    test('a key past the end of the window asks for the next one, and the focus follows to the day it was going to', async ({ page }) => {
        const requests = countRequests(page)

        await cell(page, '2026-11-08').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowRight')
        await answer
        await expect(title(page)).toHaveText('November 2026')

        // November's window is 2026-10-26 to 2026-12-06: the day is in it, and it has the focus (not the page)
        await expect(cell(page, '2026-11-09')).toBeFocused()
        expect(await tabStops(page)).toEqual(['2026-11-09'])
        expect(requests.count).toBe(1)
    })

    test('ArrowLeft past the start of the window asks for the window before it', async ({ page }) => {
        await cell(page, '2026-09-28').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowLeft')
        await answer
        await expect(title(page)).toHaveText('September 2026')
        await expect(cell(page, '2026-09-27')).toBeFocused()
        expect(await tabStops(page)).toEqual(['2026-09-27'])
    })

    test('PageDown is a month on and PageUp a month back, the focus on the same day of it', async ({ page }) => {
        await cell(page, '2026-10-09').focus()

        let answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('PageDown')
        await answer
        await expect(title(page)).toHaveText('November 2026')
        await expect(cell(page, '2026-11-09')).toBeFocused()

        answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('PageUp')
        await answer
        await expect(title(page)).toHaveText('October 2026')
        await expect(cell(page, '2026-10-09')).toBeFocused()
        expect(await tabStops(page)).toEqual(['2026-10-09'])
    })

    test('PageDown from the 31st lands on the last day of a shorter month', async ({ page }) => {
        await cell(page, '2026-10-31').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('PageDown')
        await answer
        await expect(cell(page, '2026-11-30')).toBeFocused()
    })

    test('a render around the grid leaves the focus where it is, and so does a new payload', async ({ page }) => {
        await cell(page, '2026-10-14').focus()

        let answer = page.waitForResponse(isUpdate)

        await page.locator('[data-bump]').click()
        await answer
        await cell(page, '2026-10-14').focus()
        await page.keyboard.press('ArrowRight')
        expect(await focused(page)).toBe('2026-10-15')

        // an item added by the server changes the payload: the days are drawn again, the focus is on the day it was on
        answer = page.waitForResponse(isUpdate)
        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await answer
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Added later' })).toBeVisible()
        await expect(cell(page, '2026-10-15')).toBeFocused()
    })

    test('the toolbar moves the tab stop with the month: it is not left on a day that is not there', async ({ page }) => {
        await cell(page, '2026-10-14').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.getByRole('button', { name: 'Next', exact: true }).click()
        await answer
        await expect(title(page)).toHaveText('November 2026')

        // 2026-10-14 is not in November's window, and the new tab stop is a day of the month on show
        const stops = await tabStops(page)

        expect(stops).toHaveLength(1)
        expect(stops[0].startsWith('2026-11')).toBe(true)
    })
})

test.describe('the day list: the focus goes in, and comes back to where it came from', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
    })

    test('Enter on a day opens its list with the focus in it, and Escape closes it and gives the focus back to the day', async ({ page }) => {
        const day = cell(page, '2026-10-09')

        await day.focus()
        await expect(day).toHaveAttribute('aria-expanded', 'false')
        await page.keyboard.press('Enter')

        await expect(popover(page)).toBeVisible()
        await expect(day).toHaveAttribute('aria-expanded', 'true')
        expect(await page.evaluate(() => document.activeElement.matches('[data-atom-calendar-popover]'))).toBe(true)

        await page.keyboard.press('Escape')
        await expect(popover(page)).toBeHidden()
        await expect(day).toBeFocused()
        await expect(day).toHaveAttribute('aria-expanded', 'false')
    })

    test('Space opens it as well, and a day with nothing in it opens nothing', async ({ page }) => {
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press(' ')
        await expect(popover(page)).toBeVisible()
        await page.keyboard.press('Escape')

        await cell(page, '2026-10-16').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeHidden()
        await expect(cell(page, '2026-10-16')).toBeFocused()
    })

    test('the day names the list it opens: aria-controls is the list, which is a dialog named for the day', async ({ page }) => {
        const day = cell(page, '2026-10-09')

        await expect(day).toHaveAttribute('aria-haspopup', 'dialog')
        await expect(cell(page, '2026-10-16')).not.toHaveAttribute('aria-haspopup', /.*/)

        const id = await day.getAttribute('aria-controls')

        expect(id).toBe(await popover(page).getAttribute('id'))

        await day.focus()
        await page.keyboard.press('Enter')
        await expect(probe(page).getByRole('dialog', { name: 'Friday, 9 Oct 2026' })).toBeVisible()
    })

    test('Tab through the list and out of its end closes it and goes back to the day', async ({ page }) => {
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()

        // the close button, then the three items of the day; one more leaves
        for (let i = 0; i < 4; i++) {
            await page.keyboard.press('Tab')
            expect(await page.evaluate(() => document.activeElement.closest('[data-atom-calendar-popover]') !== null)).toBe(true)
        }

        await page.keyboard.press('Tab')
        await expect(popover(page)).toBeHidden()
        await expect(cell(page, '2026-10-09')).toBeFocused()
    })

    test('Shift+Tab from the list goes back to the day it came from', async ({ page }) => {
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()
        await page.keyboard.press('Shift+Tab')
        await expect(popover(page)).toBeHidden()
        await expect(cell(page, '2026-10-09')).toBeFocused()
    })

    test('+N more opens the list too, and Escape gives the focus back to that button', async ({ page }) => {
        const more = probe(page).locator('[data-atom-calendar-more]').first()

        await more.focus()
        await expect(more).toHaveAttribute('aria-haspopup', 'dialog')
        await expect(more).toHaveAttribute('aria-expanded', 'false')
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()
        await expect(more).toHaveAttribute('aria-expanded', 'true')
        await expect(more).toHaveAttribute('aria-controls', (await popover(page).getAttribute('id')) ?? '')

        await page.keyboard.press('Escape')
        await expect(popover(page)).toBeHidden()
        await expect(more).toBeFocused()
    })

    test('a keyboard on a day inside a modal: Escape closes the list and leaves the modal open', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')
        await page.locator('[data-open-modal]').click()

        const modal = page.locator('[data-modal]')

        await expect(modal).toBeVisible()

        const day = modal.locator('[data-atom-calendar-day][data-date="2026-10-12"]')

        await day.focus()
        await page.keyboard.press('Enter')
        await expect(modal.locator('[data-atom-calendar-popover]')).toBeVisible()
        await page.keyboard.press('Escape')
        await expect(modal.locator('[data-atom-calendar-popover]')).toBeHidden()
        await expect(modal).toBeVisible()
        await expect(day).toBeFocused()
    })
})

test.describe('a calendar with no window to ask for', () => {
    test('a key past the end of the grid does nothing, and keeps the focus where it was', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const requests = countRequests(page)
        const last = cells(page, 'static').last()

        await last.focus()
        await page.keyboard.press('ArrowRight')
        await page.keyboard.press('PageDown')

        await expect(last).toBeFocused()
        expect(await tabStops(page, 'static')).toHaveLength(1)
        expect(requests.count).toBe(0)

        // inside the grid they work as anywhere
        await cells(page, 'static').first().focus()
        await page.keyboard.press('ArrowDown')
        await expect(cells(page, 'static').nth(7)).toBeFocused()
    })
})

test.describe('what the controls are called', () => {
    test('the month: a grid named for the month, cells named for their day, and a toolbar that says what each control does', async ({ page }) => {
        await open(page)

        await expect(probe(page).getByRole('grid', { name: 'October 2026' })).toBeVisible()
        await expect(probe(page).getByRole('gridcell', { name: 'Friday, 9 Oct 2026, 3 items' })).toHaveAttribute('aria-current', 'date')
        await expect(probe(page).getByRole('button', { name: 'Previous', exact: true })).toBeVisible()
        await expect(probe(page).getByRole('button', { name: 'Next', exact: true })).toBeVisible()
        await expect(probe(page).getByRole('button', { name: 'Today', exact: true })).toBeVisible()
        // what the visible name says is in the name: October 2026
        await expect(probe(page).getByRole('button', { name: 'October 2026, Choose date', exact: true })).toBeVisible()
        await expect(probe(page).getByRole('group', { name: 'View', exact: true })).toBeVisible()
        await expect(probe(page).getByRole('button', { name: 'Month', exact: true })).toHaveAttribute('aria-pressed', 'true')
    })

    test('the title is a polite live region, so a change of window is said', async ({ page }) => {
        await open(page)
        await expect(title(page)).toHaveAttribute('aria-live', 'polite')
        await expect(title(page)).toHaveText('October 2026')

        const answer = page.waitForResponse(isUpdate)

        await page.getByRole('button', { name: 'Next', exact: true }).click()
        await answer
        await expect(title(page)).toHaveText('November 2026')
    })

    test('the view buttons control the body, and the week and day are named by the title', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week')

        const body = await probe(page).locator('[data-atom-calendar-body]').getAttribute('id')

        expect(body).toBeTruthy()

        for (const name of ['Month', 'Week', 'Day']) {
            await expect(probe(page).getByRole('button', { name, exact: true })).toHaveAttribute('aria-controls', body)
        }

        await expect(probe(page).getByRole('group', { name: /October 2026/ })).toHaveCount(1)
        // the column holds the timed blocks (one, the stand-up): the all-day items are in the row above it, and are not counted
        await expect(probe(page).getByRole('group', { name: 'Friday, 9 Oct 2026, 1 item', exact: true })).toHaveAttribute('aria-current', 'date')
    })
})

test.describe('the header of a day in the week view opens that day', () => {
    test('it is a button named for the day, and a click shows the day view for it without asking the server', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week')

        const requests = countRequests(page)
        const header = probe(page).getByRole('button', { name: 'Open Friday, 9 Oct 2026 in day view', exact: true })

        await expect(header).toBeVisible()
        await expect(probe(page).locator('[data-atom-calendar-column-head]')).toHaveCount(7)

        await header.click()

        await expect(probe(page).locator('[data-atom-calendar-column]')).toHaveCount(1)
        await expect(probe(page).locator('[data-atom-calendar-column]')).toHaveAttribute('data-date', '2026-10-09')
        await expect(probe(page).getByRole('button', { name: 'Day', exact: true })).toHaveAttribute('aria-pressed', 'true')
        await expect(title(page)).toHaveText('9 October 2026')
        expect(requests.count).toBe(0)

        // the one column's header has nowhere to go: it is no longer a button, and there is no control named for opening the day
        await expect(header).toHaveCount(0)
        await expect(probe(page).locator('[data-atom-calendar-column-head][aria-disabled]')).toHaveCount(0)
        await expect(probe(page).locator('button[data-atom-calendar-column-head]')).toHaveCount(0)
    })

    test('in the day view the header is the title of the day: a group named with the long date, which is not a tab stop', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=day')

        const head = probe(page).getByRole('group', { name: 'Friday, 9 Oct 2026', exact: true })

        await expect(head).toHaveCount(1)
        await expect(head).toHaveAttribute('data-atom-calendar-column-head', '')
        await expect(head).toHaveAttribute('tabindex', '-1')
        expect(await head.evaluate((el) => el.tagName)).toBe('DIV')
    })

    test('pressing the header of a day takes the focus to the day, not to the page', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week')

        await probe(page).getByRole('button', { name: 'Open Friday, 9 Oct 2026 in day view', exact: true }).focus()
        await page.keyboard.press('Enter')

        await expect(probe(page).locator('[data-atom-calendar-column]')).toHaveAttribute('data-date', '2026-10-09')
        await expect(probe(page).locator('[data-atom-calendar-column-head]')).toBeFocused()
        expect(await page.evaluate(() => document.activeElement === document.body)).toBe(false)
    })

    test('the keyboard does the same: Enter on a focused header', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week')
        await probe(page).getByRole('button', { name: 'Open Tuesday, 6 Oct 2026 in day view', exact: true }).focus()
        await page.keyboard.press('Enter')

        await expect(probe(page).locator('[data-atom-calendar-column]')).toHaveAttribute('data-date', '2026-10-06')
    })

    test('a calendar with no bound property does it too', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-grid')

        const headers = probe(page, 'grid').getByRole('button', { name: /in day view$/ })

        await expect(headers).toHaveCount(7)
        await headers.nth(2).click()
        await expect(probe(page, 'grid').locator('[data-atom-calendar-column]')).toHaveCount(1)
        await expect(probe(page, 'grid').locator('[data-atom-calendar-column]')).toHaveAttribute('data-date', '2026-10-07')
    })
})

test.describe('a block in the week and day views is named in full', () => {
    const named = (page, name) => probe(page, 'grid').getByRole('button', { name, exact: true })

    test.beforeEach(async ({ page }) => {
        await open(page, '/atom/e2e/calendar-grid?scenario=names&view=week&scroll=08:00')
    })

    test('title, day, time range, and what its colour stands for', async ({ page }) => {
        await expect(named(page, 'Planning, Friday, 9 Oct 2026, 9:00 AM – 10:00 AM, Blue')).toBeVisible()
        await expect(named(page, 'Open end, Friday, 9 Oct 2026, 6:00 PM, Green')).toHaveCount(1)
    })

    test('an overdue one says so, and not the colour it would have had', async ({ page }) => {
        await expect(named(page, 'Server upgrade, Friday, 9 Oct 2026, 2:00 PM – 3:30 PM, Overdue')).toHaveCount(1)
    })

    test('a colour of its own, or none, has nothing to name', async ({ page }) => {
        await expect(named(page, 'Hex item, Friday, 9 Oct 2026, 4:00 PM – 5:00 PM')).toHaveCount(1)
        await expect(named(page, 'Plain item, Friday, 9 Oct 2026, 11:00 AM – 12:00 PM')).toHaveCount(1)
    })

    test('the blocks of a day come in the order they start, whatever order they were given in', async ({ page }) => {
        const names = await probe(page, 'grid').locator('[data-atom-calendar-column][data-date="2026-10-09"] [data-atom-calendar-chip="block"]').evaluateAll((els) => els.map((el) => el.getAttribute('aria-label').split(',')[0]))

        expect(names).toEqual(['Planning', 'Plain item', 'Server upgrade', 'Hex item', 'Open end'])
    })

    test('a column is a group named for its day', async ({ page }) => {
        await expect(probe(page, 'grid').getByRole('group', { name: /^Friday, 9 Oct 2026, \d+ items$/ })).toHaveAttribute('aria-current', 'date')
    })

    test('the name is the same in the day view', async ({ page }) => {
        await probe(page, 'grid').getByRole('button', { name: 'Day', exact: true }).click()
        await expect(named(page, 'Planning, Friday, 9 Oct 2026, 9:00 AM – 10:00 AM, Blue')).toBeVisible()
    })
})

test.describe('ids are derived, and two calendars do not share one', () => {
    const idsOf = (page, name) => probe(page, name).evaluate((root) => ({
        uid: root.dataset.atomCalendarUid ?? null,
        body: root.querySelector('[data-atom-calendar-body]').id,
        popover: root.querySelector('[data-atom-calendar-popover]').id,
        title: root.querySelector('[role="status"]').id,
    }))

    test('on a page of calendars, named and not, no id is on it twice', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const all = await page.evaluate(() => [...document.querySelectorAll('[id]')].map((el) => el.id))
        const duplicates = all.filter((id, i) => all.indexOf(id) !== i)

        expect(duplicates).toEqual([])

        const ids = []

        for (const name of ['static', 'flex', 'sized', 'named']) ids.push(await idsOf(page, name))

        for (const one of ids) {
            expect(one.body).toMatch(/^atom-calendar-/)
            expect(one.popover).toMatch(/^atom-calendar-/)
            expect(one.title).toMatch(/^atom-calendar-/)
        }

        expect(new Set(ids.map((one) => one.body)).size).toBe(ids.length)
        expect(new Set(ids.map((one) => one.popover)).size).toBe(ids.length)
    })

    test('a name is what the ids are built from', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const ids = await idsOf(page, 'named')

        // the slug of the name, and a hash of the name as written
        expect(ids.uid).toMatch(/^atom-calendar-team-plan-[0-9a-f]{8}$/)
        expect(ids).toEqual({
            uid: ids.uid,
            body: ids.uid + '-body',
            popover: ids.uid + '-popover',
            title: ids.uid + '-title',
        })
    })

    test('names a slug cannot tell apart, or cannot read, are still different calendars', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const uids = []

        for (const name of ['named', 'slug-team-plan', 'name-cjk-1', 'name-cjk-2', 'name-punctuation', 'name-number']) {
            uids.push((await idsOf(page, name)).uid)
        }

        expect(new Set(uids).size).toBe(uids.length)
        // a name that is all punctuation is a hash and no slug, and none ends as `atom-calendar-`
        for (const uid of uids) expect(uid).toMatch(/^atom-calendar-([a-z0-9-]+-)?[0-9a-f]{8}$/)
    })

    test('a calendar with no name is numbered by the engine, and a name that is a number is not that number', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const numbered = await idsOf(page, 'static')
        const named = await idsOf(page, 'name-number')

        // (the engine's number is in no attribute: it is the id of the body that says it)
        expect(numbered.body).toMatch(/^atom-calendar-n\d+-body$/)
        expect(named.uid).toMatch(/^atom-calendar-1-[0-9a-f]{8}$/)
        expect(named.uid).not.toBe('atom-calendar-1')
    })

    test('two components bound to a property of the same name do not collide, and keep their ids when they render again', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-twin')

        const calendars = page.locator('[data-atom-calendar]')

        await expect(calendars).toHaveCount(2)

        const read = () => calendars.evaluateAll((roots) => roots.map((root) => ({
            uid: root.dataset.atomCalendarUid,
            body: root.querySelector('[data-atom-calendar-body]').id,
            popover: root.querySelector('[data-atom-calendar-popover]').id,
        })))

        const before = await read()
        const all = await page.evaluate(() => [...document.querySelectorAll('[id]')].map((el) => el.id))

        expect(before[0].uid).toBeTruthy()
        expect(before[0].uid).not.toBe(before[1].uid)
        expect(before[0].body).not.toBe(before[1].body)
        expect(all.filter((id, i) => all.indexOf(id) !== i)).toEqual([])

        // a render of a component is a morph of its markup: what is derived is the same after it
        const answer = page.waitForResponse(isUpdate)

        await page.locator('[data-bump]').first().click()
        await answer
        await expect(page.locator('[data-renders]').first()).toHaveText('1')
        expect(await read()).toEqual(before)
    })
})

// ---- the review of phase 5 ----

// the answer to every Livewire update comes `ms` later than the browser asked for it
async function delayUpdates (page, ms) {
    await page.route((url) => url.pathname.includes('livewire') && url.pathname.endsWith('/update'), async (route) => {
        await new Promise((resolve) => setTimeout(resolve, ms))
        await route.continue()
    })
}

const inGrid = (page) => page.evaluate(() => Boolean(document.activeElement?.closest('[role="grid"]')))

test.describe('a key that is waiting for the next window does not take the focus back from where the viewer went', () => {
    test.beforeEach(async ({ page }) => {
        await open(page)
        await delayUpdates(page, 1200)
    })

    test('Shift+Tab out of the grid while the window is on its way: it lands, and the focus is where it was taken', async ({ page }) => {
        await cell(page, '2026-11-08').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowRight')
        // the key out of the grid: Shift+Tab goes to the chips of the week before it, which are the grid's own, so the viewer is put on the toolbar
        await page.getByRole('button', { name: 'Today', exact: true }).focus()
        await page.evaluate(() => { window.__away = document.activeElement })
        expect(await inGrid(page)).toBe(false)

        await answer
        await expect(title(page)).toHaveText('November 2026')
        // a frame for what the grid does after a render
        await page.waitForTimeout(200)

        expect(await page.evaluate(() => document.activeElement === window.__away)).toBe(true)
        expect(await inGrid(page)).toBe(false)
    })

    test('a click on the page, outside the grid, does the same', async ({ page }) => {
        await cell(page, '2026-11-08').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowRight')
        await page.locator('[data-renders]').click()
        await answer
        await expect(title(page)).toHaveText('November 2026')
        await page.waitForTimeout(200)

        expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true)
    })

    test('the focus going nowhere after it left (the control it went to is blurred) is not taken either', async ({ page }) => {
        await cell(page, '2026-11-08').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowRight')
        await page.getByRole('button', { name: 'Today', exact: true }).focus()
        await page.evaluate(() => document.activeElement.blur())
        await answer
        await expect(title(page)).toHaveText('November 2026')
        await page.waitForTimeout(200)

        expect(await inGrid(page)).toBe(false)
    })

    test('the key owns the focus while nobody has it: a node it left dropped it to the page, and it is put on the day the key was heading for', async ({ page }) => {
        await cell(page, '2026-11-08').focus()

        const answer = page.waitForResponse(isUpdate)

        await page.keyboard.press('ArrowRight')
        // the day it was on is taken out of the page by something else before the window comes: the focus is on the page itself
        await page.evaluate(() => document.activeElement.blur())
        expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true)

        await answer
        await expect(title(page)).toHaveText('November 2026')
        await expect(cell(page, '2026-11-09')).toBeFocused()
    })

    test('with the focus left alone, it goes to the day the key was heading for, which the old window did not have', async ({ page }) => {
        // 10-09 is a week that is not in November's window: the node that had the focus is gone when the window comes
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('PageDown')
        await expect(title(page)).toHaveText('November 2026')
        await expect(cell(page, '2026-11-09')).toBeFocused()
        expect(await tabStops(page)).toEqual(['2026-11-09'])
    })
})

test.describe('the live title is said when it changes, not when the payload does', () => {
    test('an items-only change writes nothing to it, and a month does, once', async ({ page }) => {
        await open(page)
        await page.evaluate(() => {
            window.__live = 0
            new MutationObserver((records) => { window.__live += records.length })
                .observe(document.querySelector('[data-probe="calendar"] [role="status"]'), { childList: true, characterData: true, subtree: true, attributes: true })
        })

        let answer = page.waitForResponse(isUpdate)

        await page.evaluate(() => window.Livewire.first().toggleExtra())
        await answer
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Added later' })).toBeVisible()
        await page.waitForTimeout(150)
        expect(await page.evaluate(() => window.__live)).toBe(0)

        answer = page.waitForResponse(isUpdate)
        await page.getByRole('button', { name: 'Next', exact: true }).click()
        await answer
        await expect(title(page)).toHaveText('November 2026')
        await page.waitForTimeout(150)
        expect(await page.evaluate(() => window.__live)).toBe(1)
    })
})

test.describe('an item is named in full wherever it is drawn', () => {
    const named = (page, name, scope = 'calendar') => probe(page, scope).getByRole('button', { name, exact: true })

    test('a block that crosses midnight names both ends, in either column it is drawn in', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week&date=2026-10-23')

        const name = 'Night shift, Thursday, 22 Oct 2026, 10:00 PM to Friday, 23 Oct 2026, 2:00 AM'

        await expect(named(page, name)).toHaveCount(2)
        await expect(probe(page).locator('[data-atom-calendar-column][data-date="2026-10-23"]').getByRole('button', { name, exact: true })).toHaveCount(1)
    })

    test('a block carries no tooltip beside its name', async ({ page }) => {
        await open(page, '/atom/e2e/calendar?view=week&date=2026-10-23')
        await expect(probe(page).locator('[data-atom-calendar-chip="block"]').first()).toBeVisible()
        await expect(probe(page).locator('[data-atom-calendar-chip="block"][title]')).toHaveCount(0)
    })

    test('a bar names its title, its first and last day, and its colour', async ({ page }) => {
        await open(page)
        await expect(named(page, 'Launch week, Monday, 5 Oct 2026 to Friday, 9 Oct 2026, Purple')).toHaveCount(1)
    })

    test('a day item in the month says it is for the whole day, an overdue one says so', async ({ page }) => {
        await open(page)
        await expect(named(page, 'Invoice overdue, Friday, 9 Oct 2026, All day, Overdue')).toHaveCount(1)
    })

    test('a timed item in the month: title, day, time range, colour', async ({ page }) => {
        await open(page)
        await expect(named(page, 'Dentist, Wednesday, 14 Oct 2026, 8:00 AM – 9:00 AM, Red')).toHaveCount(1)
        // a colour of its own has no name to give
        await expect(named(page, 'Hex colour, Tuesday, 20 Oct 2026, 2:00 PM – 3:00 PM')).toHaveCount(1)
    })

    test('an item that crosses midnight is named whole in the month too', async ({ page }) => {
        await open(page)
        await expect(named(page, 'Night shift, Thursday, 22 Oct 2026, 10:00 PM to Friday, 23 Oct 2026, 2:00 AM')).toHaveCount(1)
    })

    test('the rows of the day list have the same names', async ({ page }) => {
        await open(page)
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()
        await expect(popover(page).getByRole('button', { name: 'Launch week, Monday, 5 Oct 2026 to Friday, 9 Oct 2026, Purple', exact: true })).toHaveCount(1)
        await expect(popover(page).getByRole('button', { name: 'Team standup, Friday, 9 Oct 2026, 9:30 AM – 10:00 AM, Blue', exact: true })).toHaveCount(1)
    })

    test('an all-day bar in the week view is named the same way', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-grid?view=week')
        await expect(probe(page, 'grid').getByRole('button', { name: 'Launch week, Monday, 5 Oct 2026 to Friday, 9 Oct 2026, Purple', exact: true })).toHaveCount(1)
        await expect(probe(page, 'grid').getByRole('button', { name: 'Public holiday, Saturday, 10 Oct 2026, All day, Green', exact: true })).toHaveCount(1)
    })

    test('a chip has a tooltip with its whole title only where the title shown is cut short', async ({ page }) => {
        await open(page)

        const chip = (text) => probe(page).locator('[data-atom-calendar-chip="dot"]', { hasText: text }).first()

        await expect(chip('Dentist')).not.toHaveAttribute('title', /.*/)
        await chip('Dentist').hover()
        await expect(chip('Dentist')).not.toHaveAttribute('title', /.*/)

        // squeezed until it is cut: the title is on it
        await page.addStyleTag({ content: '[data-atom-calendar-chip="dot"] [data-atom-calendar-chip-title] { max-width: 20px }' })
        await page.mouse.move(0, 0)
        await chip('Dentist').hover({ position: { x: 3, y: 3 } })
        await expect(chip('Dentist')).toHaveAttribute('title', 'Dentist')
    })
})

test.describe('what a day or a "+N more" says about the list', () => {
    const readSource = (page) => page.evaluate(() => {
        const root = document.querySelector('[data-probe="calendar"]')
        const data = window.Alpine.$data(root)

        return { date: data.popoverDate, source: data.popoverSource }
    })

    test('only the one that opened the list is expanded', async ({ page }) => {
        await open(page)

        const more = probe(page).locator('[data-atom-calendar-more]').first()

        await more.focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()

        const { date } = await readSource(page)

        await expect(more).toHaveAttribute('aria-expanded', 'true')
        await expect(cell(page, date)).toHaveAttribute('aria-expanded', 'false')

        await page.keyboard.press('Escape')
        await expect(popover(page)).toBeHidden()

        await cell(page, date).focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()
        await expect(cell(page, date)).toHaveAttribute('aria-expanded', 'true')
        await expect(more).toHaveAttribute('aria-expanded', 'false')
    })
})

test.describe('Escape belongs to the day list only while the focus is in it or on what opened it', () => {
    test('with the focus somewhere else, the list stays and the page gets the key', async ({ page }) => {
        await open(page)
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()

        await page.locator('[data-bump]').focus()
        await page.evaluate(() => {
            window.__escape = null
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') window.__escape = { prevented: event.defaultPrevented } })
        })
        await page.keyboard.press('Escape')

        expect(await page.evaluate(() => window.__escape)).toEqual({ prevented: false })
        await expect(popover(page)).toBeVisible()
    })

    test('in the list it closes it, and the focus is on the trigger', async ({ page }) => {
        await open(page)
        await cell(page, '2026-10-09').focus()
        await page.keyboard.press('Enter')
        await page.keyboard.press('Escape')
        await expect(popover(page)).toBeHidden()
        await expect(cell(page, '2026-10-09')).toBeFocused()
    })
})

test.describe('the day list closing under the focus does not drop it to the page', () => {
    test('opened from a day: the focus goes to that day', async ({ page }) => {
        await open(page)

        let answer = page.waitForResponse(isUpdate)

        await page.evaluate(() => window.Livewire.first().toggleLone())
        await answer
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Lone item' })).toBeVisible()

        await cell(page, '2026-10-16').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()

        answer = page.waitForResponse(isUpdate)
        await page.evaluate(() => window.Livewire.first().toggleLone())
        await answer
        await expect(popover(page)).toBeHidden()
        await expect(cell(page, '2026-10-16')).toBeFocused()
    })

    test('opened from a "+N more" that goes with the items: the focus goes to the day that is the tab stop', async ({ page }) => {
        await open(page)

        let answer = page.waitForResponse(isUpdate)

        await page.evaluate(() => window.Livewire.first().toggleCrowd())
        await answer

        // the "+N more" of 17 October, found by where the engine put it
        const index = await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).weeks.flatMap((week) => week.more).findIndex((more) => more.date === '2026-10-17'))

        expect(index).toBeGreaterThanOrEqual(0)

        const all = probe(page).locator('[data-atom-calendar-more]')
        const before = await all.count()
        const more = all.nth(index)

        await more.focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()

        answer = page.waitForResponse(isUpdate)
        await page.evaluate(() => window.Livewire.first().toggleCrowd())
        await answer
        await expect(popover(page)).toBeHidden()
        await expect(all).toHaveCount(before - 1)

        const stop = (await tabStops(page))[0]

        expect(stop).toBeTruthy()
        await expect(cell(page, stop)).toBeFocused()
    })

    test('with the focus outside the list, closing it moves nothing', async ({ page }) => {
        await open(page)

        let answer = page.waitForResponse(isUpdate)

        await page.evaluate(() => window.Livewire.first().toggleLone())
        await answer
        await expect(probe(page).locator('[data-atom-calendar-chip]', { hasText: 'Lone item' })).toBeVisible()

        await cell(page, '2026-10-16').focus()
        await page.keyboard.press('Enter')
        await expect(popover(page)).toBeVisible()
        await page.locator('[data-bump]').focus()

        answer = page.waitForResponse(isUpdate)
        await page.evaluate(() => window.Livewire.first().toggleLone())
        await answer
        await expect(popover(page)).toBeHidden()
        await expect(page.locator('[data-bump]')).toBeFocused()
    })
})

test.describe('keys that are not the grid\'s', () => {
    test('Shift with an arrow or a page key is left alone (it is for selecting a range): nothing moves and nothing is prevented', async ({ page }) => {
        await open(page)
        await cell(page, '2026-10-14').focus()
        await page.evaluate(() => {
            window.__prevented = []
            document.addEventListener('keydown', (event) => window.__prevented.push([event.key, event.defaultPrevented]))
        })

        for (const key of ['Shift+ArrowRight', 'Shift+ArrowDown', 'Shift+PageDown', 'Shift+PageUp']) await page.keyboard.press(key)

        expect(await focused(page)).toBe('2026-10-14')
        expect((await page.evaluate(() => window.__prevented)).filter(([key]) => key !== 'Shift').every(([, prevented]) => prevented === false)).toBe(true)
        await expect(title(page)).toHaveText('October 2026')
    })

    test('on a selectable calendar Enter and Space open no list, as a click opens none', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-static')

        const day = cell(page, '2026-10-12', 'selectable')

        await day.focus()
        await page.keyboard.press('Enter')
        await page.keyboard.press(' ')
        await expect(popover(page, 'selectable')).toBeHidden()

        await day.click()
        await expect(popover(page, 'selectable')).toBeHidden()
    })
})

test.describe('a week that starts on a Sunday', () => {
    test('Home is the Sunday and End the Saturday of the row', async ({ page }) => {
        await open(page, '/atom/e2e/calendar-grid?view=month&weekStart=sunday')

        await expect(cells(page, 'grid').first()).toHaveAttribute('data-date', '2026-09-27')

        await cell(page, '2026-10-14', 'grid').focus()
        await page.keyboard.press('Home')
        await expect(cell(page, '2026-10-11', 'grid')).toBeFocused()

        await page.keyboard.press('End')
        await expect(cell(page, '2026-10-17', 'grid')).toBeFocused()
    })
})
