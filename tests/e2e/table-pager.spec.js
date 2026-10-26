import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The pinned pager (beta.10): the table's bottom bar sticks to the bottom of the screen (or of the dialog) while the table runs
// past it, so the range and the page buttons are always in reach, and sits in place at the end of the table. It sticks to whatever
// scrolls, so it is outside the sideways scroll box and the table's box clips instead of hiding its overflow. A page with a save bar
// keeps it in flow. On a real Livewire table, on the consumer's Tailwind, floor and latest.

const pager = (page) => page.locator('nav[aria-label]').filter({ has: page.locator('[aria-current="page"]') }).first()
const box = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, height: r.height } })
const scrollTo = (page, y) => page.evaluate((y) => window.scrollTo(0, y), y)

for (const tw of ['floor', 'latest']) {
  test.describe(`the pinned pager, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 800 })
    })

    for (const framed of [false, true]) {
      test(`${framed ? 'a framed' : 'a frameless'} table: scrolled to the middle the pager is at the bottom of the screen, a raised surface with a hairline above it`, async ({ page }) => {
        await open(page, { path: 'table-filter-bar', tw, extra: framed ? '&framed=1' : '' })
        await expect(pager(page)).toBeVisible()
        await scrollTo(page, 900)

        const b = await box(pager(page))
        const viewport = page.viewportSize().height

        expect(b.bottom).toBeGreaterThan(viewport - 1)
        expect(b.bottom).toBeLessThanOrEqual(viewport + 0.5)
        expect(b.top).toBeGreaterThan(viewport - 100)
        await expect(page.getByRole('button', { name: 'Go to page 2' })).toBeInViewport()

        const style = await pager(page).evaluate((el) => {
          const s = getComputedStyle(el)
          return { position: s.position, bg: s.backgroundColor, blur: s.backdropFilter, border: s.borderTopWidth, z: s.zIndex }
        })
        expect(style.position).toBe('sticky')
        // the surface at 95%: not clear, not solid
        expect(style.bg).toMatch(/(, 0\.95\)|\/ 0\.95\))$/)
        expect(style.blur).toContain('blur')
        // the hairline the box's divide-y draws above it
        expect(style.border).toBe('1px')
      })
    }

    test('at the end of the table it sits in place, below the last row, and the table ends with it', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw })
      await scrollTo(page, 100000)

      const p = await box(pager(page))
      const lastRow = await box(page.locator('[data-atom-table-rows] tr').last())
      const panel = await box(page.getByTestId('panel'))

      expect(p.top).toBeGreaterThanOrEqual(lastRow.bottom - 1)
      expect(Math.abs(p.bottom - panel.bottom)).toBeLessThan(2)

      // and where the page is shorter than the screen it is in flow, under the rows, not at the screen's bottom
      await open(page, { path: 'table-filter-bar', tw, extra: '&rows=5' })
      await expect(page.locator('[data-atom-table-rows] tr')).toHaveCount(5)
    })

    test('a table of one page has no pager to pin', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&rows=5' })

      await expect(page.locator('[data-atom-table-rows] tr')).toHaveCount(5)
      await expect(page.locator('[aria-current="page"]')).toHaveCount(0)
    })

    test('a wide table scrolls sideways inside its box while the pager stays put, outside that box', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&wide=1' })

      const scroller = page.locator('[data-atom-table-scroll]')
      expect(await scroller.evaluate((el) => el.scrollWidth > el.clientWidth + 100)).toBe(true)
      expect(await scroller.evaluate((el, nav) => el.contains(nav), await pager(page).elementHandle())).toBe(false)

      await scrollTo(page, 900)
      const before = await box(pager(page))
      expect(before.bottom).toBeLessThanOrEqual(page.viewportSize().height + 0.5)
      expect(before.bottom).toBeGreaterThan(page.viewportSize().height - 1)

      await scroller.evaluate((el) => { el.scrollLeft = 600 })
      expect(await scroller.evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)

      const after = await box(pager(page))
      expect(after.bottom).toBe(before.bottom)
      expect(after.left).toBe(before.left)
      // the page itself did not scroll sideways
      expect(await page.evaluate(() => window.scrollX)).toBe(0)
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    })

    test('in a modal it sticks to the dialog\'s own scroll, not to the page', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 700 })
      await open(page, { path: 'table-filter-bar', tw, extra: '&modal=1' })

      await page.getByTestId('open-modal').click()
      const dialog = page.locator('dialog[open]')
      await expect(dialog).toBeVisible()
      await expect(pager(page)).toBeAttached()
      // the dialog scales in: measure it at rest
      await dialog.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished)))

      await dialog.evaluate((el) => { el.scrollTop = 800 })
      expect(await dialog.evaluate((el) => el.scrollTop)).toBeGreaterThan(0)

      const d = await box(dialog)
      const p = await box(pager(page))

      // inside the dialog, at its bottom (held a padding's height above the edge: a sticky box rests in its scroller's content box)
      expect(p.bottom).toBeLessThanOrEqual(d.bottom + 0.5)
      expect(p.bottom).toBeGreaterThan(d.bottom - 40)
      expect(p.top).toBeGreaterThan(d.top)
      await expect(page.getByRole('button', { name: 'Go to page 2' })).toBeInViewport()
    })

    test(':sticky-pager="false" leaves it in flow at the end of the table', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&sticky=0' })
      await expect(pager(page)).toBeAttached()

      expect(await pager(page).evaluate((el) => getComputedStyle(el).position)).toBe('static')
      await scrollTo(page, 900)
      // far below the screen's bottom: it is not pinned
      expect((await box(pager(page))).top).toBeGreaterThan(page.viewportSize().height)
    })

    test('with a page save bar the pager stays in flow, so the two never cover each other', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&bar=1' })
      await expect(pager(page)).toBeAttached()

      expect(await pager(page).evaluate((el) => getComputedStyle(el).position)).toBe('static')

      // the save bar is the one pinned at the bottom of the screen, whole, while the table runs past it
      await scrollTo(page, 900)
      const bar = page.locator('[data-atom-save-bar]')
      await expect(bar).toBeInViewport({ ratio: 1 })
      expect((await box(pager(page))).top).toBeGreaterThan(page.viewportSize().height)

      // at the end of the page both are there, the pager above the bar
      await scrollTo(page, 100000)
      await expect(bar).toBeInViewport({ ratio: 1 })
      await expect(pager(page)).toBeInViewport({ ratio: 1 })
      const p = await box(pager(page))
      const b = await box(bar)
      expect(p.bottom).toBeLessThanOrEqual(b.top + 1)
    })
  })
}
