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
