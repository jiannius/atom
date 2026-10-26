import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:table.toolbar> on a phone with an end group (or `trashed`): the start group has a 12rem floor, so a narrow end group (an
// icon-only Export) shares the row with the search and a wide one ("Show archived" and "Reconcile selected") wraps to its own row,
// whatever the buttons say: the search is never squeezed to a sliver.

const width = (locator) => locator.evaluate((el) => el.getBoundingClientRect().width)

for (const tw of ['floor', 'latest']) {
  test.describe(`toolbar end group, ${tw} consumer build`, () => {
    test('at 390px a wide end group wraps and the search keeps its width', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 800 })
      await open(page, { path: 'table-toolbar-end', tw })

      const search = page.getByTestId('toolbar-wide').locator('[data-atom-table-search]')
      const end = page.getByTestId('toolbar-wide').locator('[data-atom-table-toolbar-end]')

      expect(await width(search)).toBeGreaterThanOrEqual(160)

      const [s, e] = await Promise.all([search.boundingBox(), end.boundingBox()])
      expect(e.y).toBeGreaterThan(s.y + s.height - 1)
    })

    test('at 390px an icon-only end group shares the search\'s row', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 800 })
      await open(page, { path: 'table-toolbar-end', tw })

      const search = page.getByTestId('toolbar-narrow').locator('[data-atom-table-search]')
      const end = page.getByTestId('toolbar-narrow').locator('[data-atom-table-toolbar-end]')
      const [s, e] = await Promise.all([search.boundingBox(), end.boundingBox()])

      expect(e.x).toBeGreaterThan(s.x + s.width)
      expect(Math.abs((e.y + e.height / 2) - (s.y + s.height / 2))).toBeLessThan(4)
      expect(await width(search)).toBeGreaterThanOrEqual(160)
    })

    test('at 390px the archived toggle shares the row and the search keeps its width', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 800 })
      await open(page, { path: 'table-toolbar-end', tw })

      expect(await width(page.getByTestId('toolbar-trashed').locator('[data-atom-table-search]'))).toBeGreaterThanOrEqual(160)
    })
  })
}

// From sm up the start group takes only the space the end group leaves and wraps its chips inside itself, so the end group (Export,
// Archive) stays on the first row at the top end however many rows the filters take. It used to be `basis-auto`: once the chips ran
// past one line the whole start group wrapped and pushed the end group onto a row of its own.
for (const tw of ['floor', 'latest']) {
  test.describe(`toolbar end group at the top end, ${tw} consumer build`, () => {
    for (const w of [1440, 1024, 768]) {
      test(`at ${w}px, with chips on two or more rows, the end group sits on the first row at the toolbar's end`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 })
        await open(page, { path: 'table-toolbar-end', tw })

        const bar = page.getByTestId('toolbar-many')
        const toolbar = bar.locator('[data-atom-table-toolbar]')
        const start = bar.locator('[data-atom-table-toolbar-start]')
        const end = bar.locator('[data-atom-table-toolbar-end]')
        const search = bar.locator('[data-atom-table-search]')
        const chips = bar.locator('[data-atom-table-filter-chip]')

        await expect(chips).toHaveCount(8)

        const [t, s, e, q] = await Promise.all([toolbar.boundingBox(), start.boundingBox(), end.boundingBox(), search.boundingBox()])
        const rows = new Set((await chips.evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top))))).size

        // the chips took more than one line, so the start group is taller than the end group's one row
        expect(rows).toBeGreaterThanOrEqual(2)
        expect(s.height).toBeGreaterThan(e.height + 10)
        // the end group is on the first row: its top is the search's top, and it is flush with the toolbar's end
        expect(Math.abs(e.y - q.y)).toBeLessThan(2)
        expect(Math.abs(e.y - s.y)).toBeLessThan(2)
        expect(Math.abs((e.x + e.width) - (t.x + t.width - 12))).toBeLessThan(2)
        // and the two groups do not overlap
        expect(s.x + s.width).toBeLessThanOrEqual(e.x + 1)
        // the search keeps its 240px
        expect(q.width).toBe(240)
      })
    }

    test('at 390px a wide end group still wraps under the start group, as it did', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 })
      await open(page, { path: 'table-toolbar-end', tw })

      const bar = page.getByTestId('toolbar-many')
      const [s, e] = await Promise.all([bar.locator('[data-atom-table-toolbar-start]').boundingBox(), bar.locator('[data-atom-table-toolbar-end]').boundingBox()])

      expect(e.y).toBeGreaterThanOrEqual(s.y + s.height - 1)
      expect(await width(bar.locator('[data-atom-table-search]'))).toBeGreaterThanOrEqual(160)
    })
  })
}

// The start group keeps a 12rem floor from sm up, measured in the toolbar's own box: in a narrow container (a sidebar column, a
// slide-over, a modal) on a wide screen a wide end group wraps under the start group, where `min-w-0` alone let the search shrink to a
// sliver beside it.
for (const tw of ['floor', 'latest']) {
  test.describe(`toolbar in a narrow container, ${tw} consumer build`, () => {
    test('a 420px box on a 1200px screen: the search keeps 12rem and the wide end group wraps', async ({ page }) => {
      await page.setViewportSize({ width: 1200, height: 900 })
      await open(page, { path: 'table-toolbar-end', tw })

      const box = page.getByTestId('toolbar-narrow-box')
      const [b, s, e, q] = await Promise.all([
        box.boundingBox(),
        box.locator('[data-atom-table-toolbar-start]').boundingBox(),
        box.locator('[data-atom-table-toolbar-end]').boundingBox(),
        box.locator('[data-atom-table-search]').boundingBox(),
      ])

      expect(b.width).toBe(420)
      expect(s.width).toBeGreaterThanOrEqual(192)
      expect(q.width).toBeGreaterThanOrEqual(192)
      // wrapped: under the start group, not squeezed beside it
      expect(e.y).toBeGreaterThanOrEqual(s.y + s.height - 1)
    })

    test('the same toolbar in a wide box keeps its end group at the top, on the right', async ({ page }) => {
      await page.setViewportSize({ width: 1200, height: 900 })
      await open(page, { path: 'table-toolbar-end', tw })

      const bar = page.getByTestId('toolbar-wide')
      const [t, s, e] = await Promise.all([bar.locator('[data-atom-table-toolbar]').boundingBox(), bar.locator('[data-atom-table-toolbar-start]').boundingBox(), bar.locator('[data-atom-table-toolbar-end]').boundingBox()])

      expect(Math.abs(e.y - s.y)).toBeLessThan(2)
      expect(Math.abs((e.x + e.width) - (t.x + t.width - 12))).toBeLessThan(2)
    })
  })
}
