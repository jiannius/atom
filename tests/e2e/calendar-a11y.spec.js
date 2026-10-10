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
        await expect(probe(page).getByRole('group', { name: /^Friday, 9 Oct 2026, \d+ items$/ })).toHaveAttribute('aria-current', 'date')
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

        // the one column's header has nowhere to go: it is said to be disabled and is out of the tab order, and keeps the focus it had
        await expect(header).toHaveAttribute('aria-disabled', 'true')
        await expect(header).toHaveAttribute('tabindex', '-1')
        await expect(header).toBeFocused()
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

        expect(await idsOf(page, 'named')).toEqual({
            uid: 'atom-calendar-team-plan',
            body: 'atom-calendar-team-plan-body',
            popover: 'atom-calendar-team-plan-popover',
            title: 'atom-calendar-team-plan-title',
        })
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
