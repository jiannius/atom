import { test, expect } from '@playwright/test'
import { SKIP_REASON, TAILWIND, measureContrast, measureSweep, openLook } from './calendar-look.js'

// The look of the calendar: every view in every state the design names (the look fixture, tests/Fixtures/CalendarLookFixture.php, clock
// frozen at 2026-10-09 10:30), in light and dark, wide and narrow.
//
// The rig ships no Tailwind, so two kinds of test live here. The first runs on the bare rig and holds what atom.css owns: the structure
// is there, a gesture's shapes sit where the layout puts them and take no pointer. The second injects Tailwind's browser build from a CDN
// (a page that cannot reach it skips them, and fails under CI) and measures what only Tailwind's colours decide: the contrast of every
// text and every cue against the ground it is actually on, and what fits in what.

const WIDE = { width: 1280, height: 900 }
const NARROW = { width: 390, height: 844 }

const states = {
    month: ['none', 'popover', 'select', 'move', 'resize', 'pending'],
    week: ['none', 'popover', 'select', 'move', 'resize', 'pending'],
    day: ['none', 'select', 'move', 'resize', 'pending'],
}

const look = (page) => page.locator('[data-probe="look"]')
const apply = (page, state) => page.evaluate((s) => window.atomLook.apply(s), state)
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
})

test.describe('with Tailwind: contrast against the ground each thing is on', () => {
    test.describe.configure({ timeout: 90000 })

    // What a sweep has to find on the page for its colours to be covered: every preset (gray has zinc's classes, so it is one) and the hex
    // items, as far as a view shows them (the day is one date, the month shows a hex item as a dot or a chip, or not at all behind "+N more").
    const PRESETS = ['black', 'blue', 'green', 'orange', 'purple', 'red', 'yellow', 'zinc']
    const required = {
        month: [...PRESETS, '#ffee00'],
        week: [...PRESETS, '#000080', '#ff8800', '#ffee00'],
        day: ['black', 'blue', 'orange', 'purple', 'red', 'yellow', 'zinc'],
    }

    // The most that may be tried and never measured (clipped or covered at every scroll position), as a share of what was tried. It was
    // none, in every view, theme, size and state, when this was written: the room is for a font that wraps a title another way.
    const MAX_UNMEASURED = 0.02

    // 4.5:1 for text, 3:1 for a cue that is not text (WCAG 1.4.3 and 1.4.11)
    for (const [size, viewport] of [['wide', WIDE], ['narrow', NARROW]]) {
        for (const theme of ['light', 'dark']) {
            for (const view of ['month', 'week', 'day']) {
                test(`${view}, ${theme}, ${size}: every text is at least 4.5:1 and every cue 3:1, in every state, over the whole of the grid`, async ({ page }) => {
                    await page.setViewportSize(viewport)

                    const ok = await openLook(page, { view, theme })

                    test.skip(!ok, SKIP_REASON)

                    const worstText = []
                    const worstCue = []
                    const gaps = []
                    let seen = 0

                    for (const state of states[view]) {
                        await apply(page, state)
                        await page.waitForTimeout(150)

                        // scrolled through: the afternoon, the late items and the columns off to the side are measured too
                        const sweep = await measureSweep(page)

                        seen += sweep.text.length + sweep.graphics.length
                        worstText.push(...sweep.text.filter((row) => row.ratio < 4.5).map((row) => `${state}: ${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg} "${row.example}"`))
                        worstCue.push(...sweep.graphics.filter((row) => row.ratio < 3).map((row) => `${state}: ${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg} "${row.example}"`))

                        // coverage, per state: every colour on the page was measured at least once, and the page has all the colours it should
                        expect(sweep.present, `${state}: colours on the page`).toEqual(expect.arrayContaining(required[view]))
                        expect(sweep.colors, `${state}: colours measured`).toEqual(expect.arrayContaining(sweep.present))

                        for (const kind of ['text', 'graphics']) {
                            if (sweep.skipped[kind] > Math.ceil(sweep.tried[kind] * MAX_UNMEASURED)) gaps.push(`${state}: ${sweep.skipped[kind]} of ${sweep.tried[kind]} ${kind} never measured: ${sweep.skippedExamples[kind].join(', ')}`)
                        }
                    }

                    // it measured something: a page that did not render would pass for want of anything to fail
                    expect(seen).toBeGreaterThan(40)
                    expect(gaps).toEqual([])
                    expect(worstText).toEqual([])
                    expect(worstCue).toEqual([])
                })
            }
        }
    }

    // The sweep has to reach what a screen does not show at first: one failing colour on an item late in the day, or in a column off to
    // the side, and it must go red. The measure of the opening screen alone is shown not to see it, which is what it used to be.
    for (const theme of ['light', 'dark']) {
        const poor = theme === 'light' ? '#d4d4d8' : '#3f3f46'
        const cases = [
            { name: 'Late call at 23:50', view: 'week', size: 'wide', viewport: WIDE, date: '2026-10-09', title: 'Late call' },
            { name: 'Night shift at 22:00', view: 'day', size: 'wide', viewport: WIDE, date: '2026-10-09', title: 'Night shift' },
            { name: 'Late call in the day view at 390', view: 'day', size: 'narrow', viewport: NARROW, date: '2026-10-09', title: 'Late call' },
            { name: 'the Saturday column, off to the right at 390', view: 'week', size: 'narrow', viewport: NARROW, date: '2026-10-10', title: 'Night shift' },
        ]

        for (const { name, view, viewport, date, title } of cases) {
            test(`${theme}: a failing colour only on ${name} goes red in the sweep, and is not in the opening screen`, async ({ page }) => {
                await page.setViewportSize(viewport)

                const ok = await openLook(page, { view, theme })

                test.skip(!ok, SKIP_REASON)

                // the text of that one block, on that one date
                await page.addStyleTag({ content: `[data-atom-calendar-column][data-date="${date}"] [data-atom-calendar-chip="block"][aria-label^="${title},"] span { color: ${poor} !important }` })
                await page.waitForTimeout(100)

                // nothing else on the page fails (the sweeps above hold that), so any low row is the one that was put there
                const low = (rows) => rows.filter((row) => row.ratio < 4.5)
                const opening = await measureContrast(page)

                expect(low(opening.text), 'the screen as it opens does not show it').toEqual([])

                const sweep = await measureSweep(page)

                expect(low(sweep.text).length, 'the sweep finds it').toBeGreaterThan(0)
            })
        }
    }

    for (const theme of ['light', 'dark']) {
        test(`${theme}: the text of a dot chip and of "+N more" keeps its contrast under a pointer`, async ({ page }) => {
            const ok = await openLook(page, { view: 'month', theme })

            test.skip(!ok, SKIP_REASON)

            // and so do the toolbar's ghost buttons, text and glyph, which take a ground of their own under a pointer
            const targets = {
                dot: look(page).locator('[data-atom-calendar-chip="dot"]').first(),
                more: look(page).locator('[data-atom-calendar-more]').first(),
                today: look(page).getByRole('button', { name: 'Today', exact: true }),
                previous: look(page).getByRole('button', { name: 'Previous', exact: true }),
                next: look(page).getByRole('button', { name: 'Next', exact: true }),
            }

            for (const [name, target] of Object.entries(targets)) {
                await target.hover()
                await page.waitForTimeout(150)

                const { text, graphics } = await measureContrast(page)

                expect(text.filter((row) => row.ratio < 4.5).map((row) => `${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg}`), name).toEqual([])
                expect(graphics.filter((row) => row.ratio < 3).map((row) => `${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg}`), name).toEqual([])
            }
        })
    }

    for (const theme of ['light', 'dark']) {
        test(`${theme}: the toolbar's buttons are ghost buttons, on the toolbar's own ground and not a box of their own, and their glyphs are measured`, async ({ page }) => {
            const ok = await openLook(page, { view: 'week', theme })

            test.skip(!ok, SKIP_REASON)

            const buttons = look(page).locator(':scope > div:first-child [data-atom-button]')

            await expect(buttons).toHaveCount(3)

            const grounds = await buttons.evaluateAll((els) => els.map((el) => ({
                name: el.getAttribute('aria-label') ?? el.innerText.trim(),
                background: getComputedStyle(el).backgroundColor,
                shadow: getComputedStyle(el).boxShadow,
                border: getComputedStyle(el).borderTopColor,
            })))

            // a ghost button is transparent with a transparent border and no shadow: the default one is a white box (a slab in dark mode)
            for (const ground of grounds) {
                expect(ground.background, ground.name).toBe('rgba(0, 0, 0, 0)')
                expect(ground.border, ground.name).toBe('rgba(0, 0, 0, 0)')
                expect(ground.shadow, ground.name).toBe('none')
            }

            // the glyphs of previous and next are cues, and the harness measures them, against the toolbar behind them
            const { graphics, text } = await measureContrast(page)
            const icons = graphics.filter((row) => row.name === 'button icon on its ground')

            expect(icons.length).toBeGreaterThan(0)
            expect(icons.every((row) => row.ratio >= 3)).toBe(true)
            expect(text.filter((row) => row.name.startsWith('toolbar')).every((row) => row.ratio >= 4.5)).toBe(true)
            expect(text.some((row) => row.name.startsWith('toolbar') && row.example === 'Today')).toBe(true)
        })
    }

    test('a hex colour has its edge moved to show on the ground of each theme, and a preset has not been touched', async ({ page }) => {
        const edgeOf = (title) => look(page).locator('[data-atom-calendar-chip="block"]', { hasText: title }).evaluate((el) => getComputedStyle(el).borderLeftColor)

        let ok = await openLook(page, { view: 'week', theme: 'light' })

        test.skip(!ok, SKIP_REASON)

        // orange #ff8800 is darkened on white; the navy is as it is
        expect(await edgeOf('Hex colour')).not.toBe('rgb(255, 136, 0)')
        expect(await edgeOf('Navy hex')).toBe('rgb(0, 0, 128)')

        ok = await openLook(page, { view: 'week', theme: 'dark' })

        // the other way round on the dark ground
        expect(await edgeOf('Hex colour')).toBe('rgb(255, 136, 0)')
        expect(await edgeOf('Navy hex')).not.toBe('rgb(0, 0, 128)')
    })
})

test.describe('with Tailwind: what fits in what', () => {
    test.describe.configure({ timeout: 60000 })

    test('a 45 minute item has room for its two lines at the default hour, and a shorter one for its one', async ({ page }) => {
        const ok = await openLook(page, { view: 'week', query: 'scroll=00:00' })

        test.skip(!ok, SKIP_REASON)

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

            test.skip(!ok, SKIP_REASON)

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

// What fits in a block is decided by the block's own width (atom.css, a container query on the block), not the screen's: a lane of three in a
// week column at 1280 is as narrow as a week column at 390. The cut is 9rem: from there on a 12-hour range ("12:30 PM – 1:30 PM") is cut off
// by an ellipsis anyway.
test.describe('on the bare rig: a block too narrow for its time drops it', () => {
    const NARROW_LANE = 9 // rem

    // every block in the grid: its title, its width, and whether any time is showing in it
    const blocks = (page) => look(page).locator('[data-atom-calendar-block]').evaluateAll((els) => els.map((el) => ({
        title: el.querySelector('[data-atom-calendar-item-title]').textContent.trim(),
        // the container query reads the block's content box: the block less its 2px of padding
        width: el.getBoundingClientRect().width - 2,
        shown: [...el.querySelectorAll('[data-atom-calendar-item-time]')].some((time) => time.checkVisibility()),
    })))

    const byTitle = (list, title) => list.find((block) => block.title === title)

    for (const [name, viewport, view, narrow, wide] of [
        ['1280, the week: a column holds three lanes', WIDE, 'week', ['Workshop A', 'Workshop B', 'Workshop C', 'Team standup', 'Design review', 'Client call', 'Planning', 'Review'], ['Lunch with Sam', 'Hex colour', 'Navy hex']],
        ['1280, the day: a lane of three is as wide as a week column', WIDE, 'day', [], ['Team standup', 'Design review', 'Client call', 'Lunch with Sam']],
        ['390, the week: every column is 6rem', NARROW, 'week', ['Workshop A', 'Design review', 'Lunch with Sam', 'Hex colour', 'Navy hex', 'Weekly sync'], []],
        ['390, the day: three lanes are narrow, a lane of one is not', NARROW, 'day', ['Team standup', 'Design review', 'Client call'], ['Lunch with Sam', 'Call the bank']],
    ]) {
        test(`${name}`, async ({ page }) => {
            await page.setViewportSize(viewport)
            await openLook(page, { view, tailwind: false })

            const list = await blocks(page)
            const em = await rem(page)

            expect(list.length).toBeGreaterThan(5)

            for (const title of narrow) expect(byTitle(list, title)?.shown, `${title} is narrow: ${JSON.stringify(byTitle(list, title))}`).toBe(false)
            for (const title of wide) expect(byTitle(list, title)?.shown, `${title} is wide: ${JSON.stringify(byTitle(list, title))}`).toBe(true)

            // and the rule is the width, for every block there is, whatever lane or column it is in
            for (const block of list) expect(block.shown, JSON.stringify(block)).toBe(block.width > NARROW_LANE * em)
        })
    }

    test('it is the block that is measured, not the screen: a wide screen has narrow blocks, and a narrow one has a wide block', async ({ page }) => {
        // 1280 wide: a lane of three is narrow. 390 wide: a lane of one in the day view is wider than that.
        await page.setViewportSize(WIDE)
        await openLook(page, { view: 'week', tailwind: false })

        const wide = await blocks(page)

        expect(byTitle(wide, 'Workshop A').shown).toBe(false)
        expect(byTitle(wide, 'Lunch with Sam').shown).toBe(true)

        await page.setViewportSize(NARROW)
        await openLook(page, { view: 'day', tailwind: false })

        const narrow = await blocks(page)

        expect(byTitle(narrow, 'Lunch with Sam').shown).toBe(true)
        expect(byTitle(narrow, 'Design review').shown).toBe(false)
    })

    test('the ghost of a move is measured the same way: the item at its new time, in a column of its own width', async ({ page }) => {
        await page.setViewportSize(NARROW)
        await openLook(page, { view: 'week', tailwind: false })
        await apply(page, 'move')

        // a week column at 390 is 6rem
        const ghost = look(page).locator('[data-atom-calendar-preview][data-kind="move"]')

        await expect(ghost).toContainText('Design review')
        expect(await ghost.locator('[data-atom-calendar-item-time]').evaluateAll((els) => els.some((el) => el.checkVisibility()))).toBe(false)
    })
})

test.describe('with Tailwind: a narrow block gives the room of its time to its title', () => {
    test.describe.configure({ timeout: 60000 })

    // how many characters of an element's text are drawn inside its own box (a character cut off by an ellipsis, or on a line clamped away, is not)
    const drawn = (el) => {
        const box = el.getBoundingClientRect()
        const node = el.firstChild
        const range = document.createRange()
        let count = 0

        for (let i = 0; i < node.length; i++) {
            range.setStart(node, i)
            range.setEnd(node, i + 1)

            const rect = range.getClientRects()[0]

            if (rect && rect.left >= box.left - 0.5 && rect.right <= box.right + 0.5 && rect.top >= box.top - 0.5 && rect.bottom <= box.bottom + 0.5) count++
        }

        return count
    }

    const titleOf = (page, title) => look(page).locator('[data-atom-calendar-block]', { hasText: title }).first().locator('[data-atom-calendar-item-title]')

    // the page as it was drawn before: atom.css's container rule taken out
    const withoutTheRule = (page) => page.evaluate(() => {
        for (const sheet of document.styleSheets) {
            try {
                for (let i = sheet.cssRules.length - 1; i >= 0; i--) {
                    if (sheet.cssRules[i] instanceof CSSContainerRule && sheet.cssRules[i].containerName === 'atom-calendar-block') sheet.deleteRule(i)
                }
            } catch {}
        }
    })

    for (const [name, viewport, view, narrow, wide] of [
        ['1280, the week', WIDE, 'week', ['Workshop A', 'Design review'], ['Lunch with Sam']],
        // the lanes are narrow here, but these titles fit one line already: nothing to give them
        ['390, the day', NARROW, 'day', [], ['Design review', 'Team standup', 'Lunch with Sam']],
        ['390, the week (a short item is its title and its time on one line)', NARROW, 'week', ['Weekly sync', 'Workshop A'], []],
    ]) {
        for (const theme of ['light', 'dark']) {
            test(`${name}, ${theme}: a narrow block's title that did not fit shows more of itself than it did, one that fitted is as it was`, async ({ page }) => {
                await page.setViewportSize(viewport)

                const ok = await openLook(page, { view, theme, query: 'scroll=08:00' })

                test.skip(!ok, SKIP_REASON)

                const after = {}
                const before = {}

                for (const title of [...narrow, ...wide]) after[title] = await titleOf(page, title).evaluate(drawn)

                await withoutTheRule(page)
                await page.waitForTimeout(100)

                for (const title of [...narrow, ...wide]) before[title] = await titleOf(page, title).evaluate(drawn)

                for (const title of narrow) expect(after[title], `${title}: ${after[title]} characters, they were ${before[title]}`).toBeGreaterThan(before[title])
                for (const title of wide) expect(after[title], title).toBe(before[title])
            })
        }
    }

    test('the title of a narrow block is clamped to two lines and never spills out of the block', async ({ page }) => {
        await page.setViewportSize(WIDE)

        const ok = await openLook(page, { view: 'week', query: 'scroll=08:00' })

        test.skip(!ok, SKIP_REASON)

        expect(await titleOf(page, 'Workshop A').evaluate((el) => getComputedStyle(el).webkitLineClamp)).toBe('2')

        const fits = await look(page).locator('[data-atom-calendar-block]', { hasText: 'Workshop A' }).first().evaluate((block) => {
            const inner = block.querySelector('[data-atom-calendar-chip]').getBoundingClientRect()
            const text = block.querySelector('[data-atom-calendar-item-title]').getBoundingClientRect()

            return text.bottom <= inner.bottom + 0.5 && text.right <= inner.right + 0.5
        })

        expect(fits).toBe(true)
    })
})

// A title in a narrow block wraps between words and nowhere else: a line never starts or ends inside a word. A word wider than its lane is
// not split ("Worksh" / "op A"): it stays whole on its line and is cut off by an ellipsis.
test.describe('a narrow block breaks its title between words, and cuts a word that is too long', () => {
    test.describe.configure({ timeout: 60000 })

    // every title of a block in the grid, with the lines it is drawn in (a Range per character, grouped by the line it is on) and whether a
    // line is cut off at the right
    const titles = (page) => look(page).locator('[data-atom-calendar-block] [data-atom-calendar-item-title]').evaluateAll((els) => els.map((el) => {
        const node = el.firstChild
        const range = document.createRange()
        const lines = []

        for (let i = 0; i < node.length; i++) {
            range.setStart(node, i)
            range.setEnd(node, i + 1)

            const rect = range.getClientRects()[0]

            if (!rect) continue

            const line = lines.at(-1)

            if (!line || Math.abs(rect.top - line.top) > 3) lines.push({ top: rect.top, from: i, to: i + 1 })
            else line.to = i + 1
        }

        const text = node.data
        const style = getComputedStyle(el)

        return {
            text,
            // a line begins at a word when the character before it is a space (or there is none), and ends at one when the next is
            splits: lines.flatMap((line) => {
                const start = text.slice(line.from, line.to).search(/\S/)
                const end = text.slice(line.from, line.to).trimEnd().length

                if (start === -1) return []

                const first = line.from + start
                const last = line.from + end

                // a hyphen is a place a word may break, as it is in every browser: "check-" / "in" is two words' worth of line
                const inside = (at) => at > 0 && at < text.length && /\S/.test(text[at - 1]) && /\S/.test(text[at]) && text[at - 1] !== '-'

                return [first, last].filter(inside).map((at) => text.slice(0, at) + ' | ' + text.slice(at))
            }),
            lines: lines.length,
            cut: el.scrollWidth > el.clientWidth + 0.5,
            overflow: style.textOverflow,
        }
    }))

    for (const [name, viewport, view, tailwind] of [
        ['1280, the week', WIDE, 'week', true],
        ['1280, the day', WIDE, 'day', true],
        ['390, the week', NARROW, 'week', true],
        ['390, the day', NARROW, 'day', true],
        ['1280, the week, on the bare rig (a larger type, so more words are too long)', WIDE, 'week', false],
        ['390, the week, on the bare rig', NARROW, 'week', false],
    ]) {
        test(`${name}: no line starts or ends inside a word`, async ({ page }) => {
            await page.setViewportSize(viewport)

            const ok = await openLook(page, { view, tailwind, query: 'scroll=08:00' })

            test.skip(!ok, SKIP_REASON)

            const list = await titles(page)

            expect(list.length).toBeGreaterThan(5)

            for (const title of list) expect(title.splits, `"${title.text}" is split: ${JSON.stringify(title.splits)}`).toEqual([])

            // not vacuous: in the week a lane of three is narrower than a word, and those titles do wrap; with Tailwind's type one word is wider than its lane
            if (view === 'week') expect(list.filter((title) => title.lines > 1).length, 'some title wraps').toBeGreaterThan(0)
            if (view === 'week' && tailwind) expect(list.filter((title) => title.cut).length, 'some word is too long for its lane').toBeGreaterThan(0)
        })
    }

    test('a word too long for its lane is cut by an ellipsis, and a title of several words is clamped to two lines', async ({ page }) => {
        await page.setViewportSize(WIDE)

        const ok = await openLook(page, { view: 'week', query: 'scroll=08:00' })

        test.skip(!ok, SKIP_REASON)

        const list = await titles(page)
        const cut = list.filter((title) => title.cut)

        expect(cut.length).toBeGreaterThan(0)

        for (const title of cut) expect(title.overflow, title.text).toBe('ellipsis')

        // a block of several words keeps its two lines (or its single one) and is not taller than that
        const tall = await look(page).locator('[data-atom-calendar-block]', { hasText: 'Workshop A' }).first().locator('[data-atom-calendar-item-title]').evaluate((el) => ({
            height: el.getBoundingClientRect().height,
            line: parseFloat(getComputedStyle(el).lineHeight),
        }))

        expect(tall.height).toBeLessThanOrEqual(tall.line * 2 + 1)
    })
})

test.describe('on the bare rig: a preview that changes leaves the blocks alone', () => {
    // every record the page's mutation observer makes about a block: the node itself added or removed, or anything inside it changed
    const watchBlocks = (page) => page.evaluate(() => {
        window.__blockRecords = []

        const blockOf = (node) => (node.nodeType === 1 ? node : node.parentElement)?.closest('[data-atom-calendar-block]')
        const isBlock = (node) => node.nodeType === 1 && node.matches('[data-atom-calendar-block]')

        window.__blockOf = blockOf
        window.__blockObserver = new MutationObserver((list) => {
            for (const record of list) {
                const block = blockOf(record.target)

                if (block || [...record.addedNodes, ...record.removedNodes].some(isBlock)) {
                    window.__blockRecords.push({ type: record.type, attribute: record.attributeName, target: record.target.nodeName, title: block?.querySelector('[data-atom-calendar-item-title]')?.textContent.trim() ?? null })
                }
            }
        })
        window.__blockObserver.observe(document.querySelector('[data-atom-calendar-grid-body]'), { attributes: true, childList: true, characterData: true, subtree: true })
    })

    const records = (page) => page.evaluate(() => {
        // what is queued has not been delivered yet: deliver it, the same way
        const queued = window.__blockObserver.takeRecords()

        for (const record of queued) {
            const block = window.__blockOf(record.target)

            if (block || [...record.addedNodes, ...record.removedNodes].some((node) => node.nodeType === 1 && node.matches('[data-atom-calendar-block]'))) {
                window.__blockRecords.push({ type: record.type, attribute: record.attributeName, target: record.target.nodeName, title: block?.querySelector('[data-atom-calendar-item-title]')?.textContent.trim() ?? null })
            }
        }

        return window.__blockRecords
    })

    test('a selection, an item waiting for the host, and none again, touch no node of any block', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })
        await watchBlocks(page)

        const shapes = look(page).locator('[data-atom-calendar-preview]')

        for (const state of ['select', 'pending', 'select', 'none', 'pending', 'select', 'none']) {
            await apply(page, state)
            await page.waitForTimeout(120)
            await expect(shapes).toHaveCount(state === 'none' ? 0 : 1)
        }

        expect(await records(page)).toEqual([])
    })

    test('a move or a resize touches one thing in the grid: the lifted attribute of the original', async ({ page }) => {
        await openLook(page, { view: 'week', tailwind: false })
        await watchBlocks(page)

        for (const state of ['move', 'resize', 'none', 'move', 'select']) {
            await apply(page, state)
            await page.waitForTimeout(120)
        }

        const changed = await records(page)

        expect(changed.length).toBeGreaterThan(0)
        // only the chip of the original changes, and only its lifted flag: no block is added, removed or rewritten
        expect([...new Set(changed.map((record) => `${record.type} ${record.attribute} ${record.target}`))]).toEqual(['attributes data-lifted A'])
        expect([...new Set(changed.map((record) => record.title))].sort()).toEqual(['Client call', 'Design review'])
    })
})

test.describe('on the bare rig: a host overlay is over the month, ghost and all', () => {
    test('a fixed overlay with z-index 1 over the ghost of a move is what is on top there', async ({ page }) => {
        await openLook(page, { view: 'month', tailwind: false })
        await apply(page, 'move')

        // the ghost takes no pointer, so every part of the calendar is let take one for the hit test, as the contrast sweep does
        await page.addStyleTag({ content: '[data-atom-calendar] * { pointer-events: auto !important }' })

        const ghost = await look(page).locator('[data-atom-calendar-ghost]').boundingBox()
        const top = await page.evaluate((box) => {
            const overlay = document.createElement('div')

            overlay.id = 'host-overlay'
            overlay.style.cssText = `position:fixed;z-index:1;left:${box.x}px;top:${box.y}px;width:${box.width}px;height:${box.height}px;background:red`
            document.body.append(overlay)

            return document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2)?.id
        }, ghost)

        expect(top).toBe('host-overlay')
    })
})

test.describe('with Tailwind: a shape of a gesture is on a ground of its own', () => {
    // what the shape itself paints at a point near its top: its own layers (the element and what is inside it), composited, and how opaque that is
    const alphaOf = (page, kind) => page.evaluate((kind) => {
        const unblock = document.createElement('style')

        unblock.textContent = '[data-atom-calendar] * { pointer-events: auto !important }'
        document.head.append(unblock)

        const shape = document.querySelector(`[data-atom-calendar-preview][data-kind="${kind}"]`)
        const rect = shape.getBoundingClientRect()
        const canvas = document.createElement('canvas')

        canvas.width = canvas.height = 1

        const context = canvas.getContext('2d', { willReadFrequently: true })
        const alpha = (value) => {
            context.clearRect(0, 0, 1, 1)
            context.fillStyle = '#000'
            context.fillStyle = value
            context.fillRect(0, 0, 1, 1)

            return context.getImageData(0, 0, 1, 1).data[3] / 255
        }

        // near the top and in from the side, where the label is
        const layers = document.elementsFromPoint(rect.left + rect.width / 2, rect.top + 6).filter((el) => shape.contains(el))
        const covered = layers.reduceRight((below, el) => below + (1 - below) * alpha(getComputedStyle(el).backgroundColor), 0)

        unblock.remove()

        return { layers: layers.length, alpha: covered }
    }, kind)

    for (const theme of ['light', 'dark']) {
        test(`${theme}: a selection, an item waiting for the host, and the ghost of a move or a resize do not show what is under them`, async ({ page }) => {
            const ok = await openLook(page, { view: 'week', theme, query: 'scroll=09:00' })

            test.skip(!ok, SKIP_REASON)

            for (const kind of ['select', 'pending', 'move', 'resize']) {
                await apply(page, kind)
                await page.waitForTimeout(150)

                const { layers, alpha } = await alphaOf(page, kind)

                expect(layers, kind).toBeGreaterThan(0)
                expect(alpha, `${kind} paints a ground of its own`).toBeGreaterThan(0.999)
            }
        })
    }
})

test.describe('what the page does when Tailwind cannot be loaded', () => {
    // the CDN made unreachable for this page
    const offline = (page) => page.route('**/@tailwindcss/browser@4**', (route) => route.abort())

    test('under CI it fails, loudly, naming what could not be loaded', async ({ page }) => {
        const was = process.env.CI

        process.env.CI = '1'

        try {
            await offline(page)
            await expect(openLook(page, { view: 'week' })).rejects.toThrow(TAILWIND)
        } finally {
            if (was === undefined) delete process.env.CI
            else process.env.CI = was
        }
    })

    test('outside CI it says so on the console and answers false, which the tests turn into a skip whose reason says it is not a pass', async ({ page }) => {
        const was = process.env.CI
        const warn = console.warn
        const warnings = []

        delete process.env.CI
        console.warn = (...args) => { warnings.push(args.join(' ')) }

        try {
            await offline(page)

            expect(await openLook(page, { view: 'week' })).toBe(false)
        } finally {
            console.warn = warn

            if (was !== undefined) process.env.CI = was
        }

        expect(warnings.join('\n')).toContain('SKIPPED, not passed')
        expect(SKIP_REASON).toContain('NOT PASSED')
    })
})
