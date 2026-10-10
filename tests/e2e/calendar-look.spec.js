import { test, expect } from '@playwright/test'
import { measureContrast, openLook } from './calendar-look.js'

// The look of the calendar: every view in every state the design names (the look fixture, tests/Fixtures/CalendarLookFixture.php, clock
// frozen at 2026-10-09 10:30), in light and dark, wide and narrow.
//
// The rig ships no Tailwind, so two kinds of test live here. The first runs on the bare rig and holds what atom.css owns: the structure
// is there, a gesture's shapes sit where the layout puts them and take no pointer. The second injects Tailwind's browser build from a CDN
// (a page that cannot reach it skips them) and measures what only Tailwind's colours decide: the contrast of every text and every cue
// against the ground it is actually on, and what fits in what.

const WIDE = { width: 1280, height: 900 }
const NARROW = { width: 390, height: 844 }

const states = {
    month: ['none', 'popover', 'select', 'move', 'resize', 'pending'],
    week: ['none', 'popover', 'select', 'move', 'resize', 'pending'],
    day: ['none', 'select', 'move', 'resize', 'pending'],
}

const look = (page) => page.locator('[data-probe="look"]')
const apply = (page, state, overlap = 'lanes') => page.evaluate(([s, o]) => window.atomLook.apply(s, o), [state, overlap])
const rem = (page) => page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))

test.describe('on the bare rig: the structure and the shapes of a gesture', () => {
    test('the month has its six weeks and 42 days, the week seven columns of 24 hours, the day one', async ({ page }) => {
        await openLook(page, { view: 'month', tailwind: false })
        await expect(look(page).locator('[data-atom-calendar-week]')).toHaveCount(6)
        await expect(look(page).locator('[data-atom-calendar-day]')).toHaveCount(42)
        await expect(look(page).locator('[data-atom-calendar-chip="bar"]').first()).toBeVisible()

        await look(page).getByRole('button', { name: 'Week', exact: true }).click()
        await expect(look(page).locator('[data-atom-calendar-column]')).toHaveCount(7)
        await expect(look(page).locator('[data-atom-calendar-hour]')).toHaveCount(24)

        await look(page).getByRole('button', { name: 'Day', exact: true }).click()
        await expect(look(page).locator('[data-atom-calendar-column]')).toHaveCount(1)
    })

    test('the page says which state to open in: ?state=select shows a selection and nothing else', async ({ page }) => {
        await openLook(page, { view: 'week', query: 'state=select', tailwind: false })

        const shapes = look(page).locator('[data-atom-calendar-preview]')

        await expect(shapes).toHaveCount(1)
        await expect(shapes).toHaveAttribute('data-kind', 'select')
        await expect(shapes).toContainText('11:00 AM – 12:30 PM')
    })

    test('a selection in the week sits in its column at the hours it names, and takes no pointer', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })
        await apply(page, 'select')

        const shape = look(page).locator('[data-atom-calendar-preview][data-kind="select"]')
        const column = look(page).locator('[data-atom-calendar-column][data-date="2026-10-08"]')
        const box = await shape.boundingBox()
        const col = await column.boundingBox()
        const hour = await rem(page) * 3.5

        await expect(shape).toBeVisible()
        expect(box.x).toBeGreaterThanOrEqual(col.x - 0.5)
        expect(box.x + box.width).toBeLessThanOrEqual(col.x + col.width + 0.5)
        // 11:00 to 12:30 is eleven hours down the day and an hour and a half tall
        expect(box.y - col.y).toBeCloseTo(11 * hour, 0)
        expect(box.height).toBeCloseTo(1.5 * hour, 0)

        // a pointer goes through it to what is under it
        const under = await page.evaluate(([x, y]) => document.elementFromPoint(x, y)?.closest('[data-atom-calendar-preview]') !== null, [box.x + box.width / 2, box.y + box.height / 2])

        expect(under).toBe(false)
    })

    test('a resize keeps the lane of the item, and the original is lifted while the ghost is up', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })
        await apply(page, 'resize')

        const ghost = look(page).locator('[data-atom-calendar-preview][data-kind="resize"]')
        const original = look(page).locator('[data-atom-calendar-block]', { hasText: 'Client call' })
        const ghostBox = await ghost.boundingBox()
        const lane = await original.boundingBox()

        expect(ghostBox.x).toBeCloseTo(lane.x, 0)
        expect(ghostBox.width).toBeCloseTo(lane.width, 0)
        expect(ghostBox.y).toBeCloseTo(lane.y, 0)
        // the end moved: a quarter of an hour past two hours
        expect(ghostBox.height).toBeCloseTo(2.25 * await rem(page) * 3.5, 0)
        await expect(look(page).locator('[data-atom-calendar-chip][data-lifted]')).toHaveCount(1)
        await expect(look(page).locator('[data-atom-calendar-chip][data-lifted]')).toContainText('Client call')
        await expect(ghost).toContainText('12:15 PM')

        // back to nothing
        await apply(page, 'none')
        await expect(look(page).locator('[data-atom-calendar-preview]')).toHaveCount(0)
        await expect(look(page).locator('[data-atom-calendar-chip][data-lifted]')).toHaveCount(0)
    })

    test('a move shows the item at the new time in the other day, and the original stays put', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })

        const before = await look(page).locator('[data-atom-calendar-block]', { hasText: 'Design review' }).boundingBox()

        await apply(page, 'move')

        const ghost = look(page).locator('[data-atom-calendar-column][data-date="2026-10-08"] [data-atom-calendar-preview][data-kind="move"]')

        await expect(ghost).toContainText('Design review')
        await expect(ghost).toContainText('1:30 PM – 3:00 PM')

        const after = await look(page).locator('[data-atom-calendar-block]', { hasText: 'Design review' }).first().boundingBox()

        expect(after).toEqual(before)
    })

    test('an item waiting for the host is up with its title, and a spinner', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })
        await apply(page, 'pending')

        const shape = look(page).locator('[data-atom-calendar-preview][data-kind="pending"]')

        await expect(shape).toContainText('New task')
        await expect(shape.locator('svg')).toHaveCount(1)

        // in the month it is a striped box over the days, and a bar with the title and the spinner in front of whatever is on them
        await openLook(page, { view: 'month', tailwind: false })
        await apply(page, 'pending')

        await expect(look(page).locator('[data-atom-calendar-selection][data-kind="pending"]')).toHaveCount(1)

        const bar = look(page).locator('[data-atom-calendar-ghost][data-kind="pending"]')

        await expect(bar).toContainText('New task')
        await expect(bar.locator('svg')).toHaveCount(1)
    })

    test('in the month, a selection is a box over its days under the chips, a move a ghost bar among them', async ({ page }) => {
        await openLook(page, { view: 'month', tailwind: false })
        await apply(page, 'select')

        const selection = look(page).locator('[data-atom-calendar-selection][data-kind="select"]')
        const week = look(page).locator('[data-atom-calendar-week]').nth(2)
        const box = await selection.boundingBox()
        const weekBox = await week.boundingBox()

        // 12 to 14 October: Monday to Wednesday of the third week, three of seven days
        expect(box.x).toBeCloseTo(weekBox.x, 0)
        expect(box.width).toBeCloseTo(weekBox.width * 3 / 7, 0)

        // it is under the chips and lets a click through
        expect(await selection.evaluate((el) => getComputedStyle(el).pointerEvents)).toBe('none')

        await apply(page, 'move')

        const ghost = look(page).locator('[data-atom-calendar-ghost]')

        // 13 to 17 October is within one week: one ghost; the original runs over two, and is lifted in both
        await expect(ghost).toHaveCount(1)
        await expect(ghost).toContainText('Conference')
        expect(await ghost.evaluate((el) => getComputedStyle(el).pointerEvents)).toBe('none')
        await expect(look(page).locator('[data-atom-calendar-chip][data-lifted]')).toHaveCount(2)
    })

    test('the day list opens from the state flag, in the month and in the week', async ({ page }) => {
        await openLook(page, { view: 'month', query: 'state=popover', tailwind: false })
        await expect(look(page).locator('[data-atom-calendar-popover]')).toBeVisible()
        await expect(look(page).locator('[data-atom-calendar-popover]')).toContainText('Breakfast meeting')

        await openLook(page, { view: 'week', query: 'state=popover', tailwind: false })
        await expect(look(page).locator('[data-atom-calendar-popover]')).toBeVisible()
    })

    test('the cascade lays overlapping items one over the next, and the default is side by side', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })

        const widths = async () => {
            const boxes = {}

            for (const name of ['Workshop A', 'Workshop B', 'Workshop C']) boxes[name] = await look(page).locator('[data-atom-calendar-block]', { hasText: name }).boundingBox()

            return boxes
        }

        const lanes = await widths()

        // three in a column, each about a third of it, none over another
        expect(lanes['Workshop A'].width).toBeCloseTo(lanes['Workshop C'].width, 0)
        expect(lanes['Workshop A'].x + lanes['Workshop A'].width).toBeLessThanOrEqual(lanes['Workshop B'].x + 1)

        await apply(page, 'none', 'cascade')

        const cascade = await widths()

        // C is in front of A and B and wider than a lane; A and B still share their level
        expect(cascade['Workshop C'].width).toBeGreaterThan(lanes['Workshop C'].width * 1.5)
        expect(cascade['Workshop A'].x + cascade['Workshop A'].width).toBeLessThanOrEqual(cascade['Workshop B'].x + 1)
        expect(cascade['Workshop C'].x).toBeGreaterThan(cascade['Workshop A'].x)

        // C is over A where they overlap; a pointer on the part of A that shows brings A to the front, so what was under C can be read and reached
        const a = cascade['Workshop A']
        const c = cascade['Workshop C']
        const covered = [c.x + 4, c.y + 4]
        const topAt = ([x, y]) => page.evaluate(([px, py]) => document.elementFromPoint(px, py)?.closest('[data-atom-calendar-block]')?.textContent.trim().slice(0, 10), [x, y])

        expect(await topAt(covered)).toBe('Workshop C')

        await page.mouse.move(a.x + 4, a.y + 4)
        expect(await topAt(covered)).toBe('Workshop A')

        // and back, when the pointer goes
        await page.mouse.move(0, 0)
        expect(await topAt(covered)).toBe('Workshop C')
    })
})

test.describe('with Tailwind: contrast against the ground each thing is on', () => {
    test.describe.configure({ timeout: 90000 })

    // 4.5:1 for text, 3:1 for a cue that is not text (WCAG 1.4.3 and 1.4.11)
    for (const [size, viewport] of [['wide', WIDE], ['narrow', NARROW]]) {
        for (const theme of ['light', 'dark']) {
            for (const view of ['month', 'week', 'day']) {
                test(`${view}, ${theme}, ${size}: every text is at least 4.5:1 and every cue 3:1, in every state`, async ({ page }) => {
                    await page.setViewportSize(viewport)

                    const ok = await openLook(page, { view, theme })

                    test.skip(!ok, 'needs Tailwind from the CDN')

                    const worstText = []
                    const worstCue = []
                    let seen = 0

                    for (const state of states[view]) {
                        await apply(page, state)
                        await page.waitForTimeout(150)

                        const { text, graphics } = await measureContrast(page)

                        seen += text.length + graphics.length
                        worstText.push(...text.filter((row) => row.ratio < 4.5).map((row) => `${state}: ${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg} "${row.example}"`))
                        worstCue.push(...graphics.filter((row) => row.ratio < 3).map((row) => `${state}: ${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg} "${row.example}"`))
                    }

                    // it measured something: a page that did not render would pass for want of anything to fail
                    expect(seen).toBeGreaterThan(40)
                    expect(worstText).toEqual([])
                    expect(worstCue).toEqual([])
                })
            }
        }
    }

    for (const theme of ['light', 'dark']) {
        test(`${theme}: the text of a dot chip and of "+N more" keeps its contrast under a pointer`, async ({ page }) => {
            const ok = await openLook(page, { view: 'month', theme })

            test.skip(!ok, 'needs Tailwind from the CDN')

            for (const selector of ['[data-atom-calendar-chip="dot"]', '[data-atom-calendar-more]']) {
                await look(page).locator(selector).first().hover()
                await page.waitForTimeout(100)

                const { text } = await measureContrast(page)

                expect(text.filter((row) => row.ratio < 4.5).map((row) => `${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg}`), selector).toEqual([])
            }
        })
    }

    test('a hex colour has its edge moved to show on the ground of each theme, and a preset has not been touched', async ({ page }) => {
        const edgeOf = (title) => look(page).locator('[data-atom-calendar-chip="block"]', { hasText: title }).evaluate((el) => getComputedStyle(el).borderLeftColor)

        let ok = await openLook(page, { view: 'week', theme: 'light' })

        test.skip(!ok, 'needs Tailwind from the CDN')

        // orange #ff8800 is darkened on white; the navy is as it is
        expect(await edgeOf('Hex colour')).not.toBe('rgb(255, 136, 0)')
        expect(await edgeOf('Navy hex')).toBe('rgb(0, 0, 128)')

        ok = await openLook(page, { view: 'week', theme: 'dark' })

        // the other way round on the dark ground
        expect(await edgeOf('Hex colour')).toBe('rgb(255, 136, 0)')
        expect(await edgeOf('Navy hex')).not.toBe('rgb(0, 0, 128)')
    })
})

test.describe('with Tailwind: a block over another', () => {
    for (const theme of ['light', 'dark']) {
        test(`${theme}: has an opaque ground under its tint, so the one it is over does not show through`, async ({ page }) => {
            const ok = await openLook(page, { view: 'week', theme })

            test.skip(!ok, 'needs Tailwind from the CDN')

            await apply(page, 'none', 'cascade')

            // C is over A and B; the tint of a block is translucent in dark mode
            const ground = await look(page).locator('[data-atom-calendar-block]', { hasText: 'Workshop C' }).evaluate((el) => getComputedStyle(el.firstElementChild).backgroundColor)

            // a colour with no alpha: Chromium writes a see-through one as `rgb(0 0 0 / 50%)` or `rgba(..)`, and nothing at all as `rgba(0, 0, 0, 0)`
            expect(ground).not.toMatch(/\/|rgba|transparent/)
        })
    }
})

test.describe('with Tailwind: what fits in what', () => {
    test.describe.configure({ timeout: 60000 })

    test('a 45 minute item has room for its two lines at the default hour, and a shorter one for its one', async ({ page }) => {
        const ok = await openLook(page, { view: 'week', query: 'scroll=00:00' })

        test.skip(!ok, 'needs Tailwind from the CDN')

        await page.evaluate(() => {
            const engine = window.atomLook.engine()
            const pad = (n) => String(n).padStart(2, '0')
            const item = (id, minutes, start) => {
                const end = Number(start.slice(0, 2)) * 60 + Number(start.slice(3)) + minutes

                return { id, title: 'Item ' + minutes, start: '2026-10-07T' + start, end: '2026-10-07T' + pad(Math.floor(end / 60)) + ':' + pad(end % 60), allDay: false, color: 'blue', icon: null, danger: false, href: null }
            }

            engine.payload.items = [item('a', 30, '01:00'), item('b', 44, '03:00'), item('c', 45, '05:00'), item('d', 46, '07:00'), item('e', 60, '09:00')]
        })
        await page.waitForTimeout(300)

        const rows = await look(page).locator('[data-atom-calendar-grid] [data-atom-calendar-chip="block"]').evaluateAll((chips) => chips.map((chip) => ({
            title: chip.textContent.trim().replace(/\s+/g, ' ').slice(0, 14),
            compact: chip.hasAttribute('data-compact'),
            overflow: chip.scrollHeight - chip.clientHeight,
        })))

        expect(rows).toHaveLength(5)

        for (const row of rows) expect(row.overflow, row.title).toBeLessThanOrEqual(1)

        // under 45 minutes is one line, from 45 two
        expect(rows.map((row) => row.compact)).toEqual([true, true, false, false, false])
    })

    for (const [name, view] of [['day', 'day'], ['week', 'week']]) {
        test(`the now line stays inside today's column at 390 wide, in the ${name} view`, async ({ page }) => {
            await page.setViewportSize(NARROW)

            const ok = await openLook(page, { view })

            test.skip(!ok, 'needs Tailwind from the CDN')

            const line = await look(page).locator('[data-atom-calendar-now-line]').boundingBox()
            const column = await look(page).locator('[data-atom-calendar-column][data-date="2026-10-09"]').boundingBox()
            const grid = await look(page).locator('[data-atom-calendar-grid]').boundingBox()

            expect(line.x).toBeGreaterThanOrEqual(column.x - 0.5)
            expect(line.x + line.width).toBeLessThanOrEqual(column.x + column.width + 0.5)

            // in the day view the column is the whole width, so the line is inside the scroller that clips it, and its dot is not cut off; in
            // the week today's column is further right than the screen is wide, and is scrolled to
            if (view === 'day') {
                expect(line.x).toBeGreaterThanOrEqual(grid.x - 0.5)
                expect(line.x + line.width).toBeLessThanOrEqual(grid.x + grid.width + 0.5)
            }
        })
    }
})
