import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The row count at the toolbar's end ("1–50 of 128", beta.10), on a real Livewire round trip. The toolbar is in the table's header
// slot, an Alpine template a re-render never refreshes, so the count is read off a hidden element the table prints outside it
// (resources/js/alpinejs/table-count.js): the count has to follow every filter, search, page and rows-per-page change without a reload.

const count = (page) => page.locator('[data-atom-table-count]')
const text = async (locator) => (await locator.innerText()).replace(/\s+/g, ' ').trim()
const option = (page, label) => page.locator('[data-atom-option]').filter({ hasText: label }).first()

for (const tw of ['floor', 'latest']) {
  test.describe(`the row count, ${tw} consumer build`, () => {
    test('reads "1–50 of 128", in the end group, ahead of the end slot, in tabular figures', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filter-bar', tw })

      await expect(count(page)).toHaveText('1–50 of 128')

      const end = await page.locator('[data-atom-table-toolbar-end]').boundingBox()
      const number = await count(page).boundingBox()
      const exportButton = await page.getByRole('button', { name: 'Export' }).boundingBox()
      const toolbar = await page.locator('[data-atom-table-toolbar]').boundingBox()

      // inside the end group, before the host's button, at the top end of the toolbar
      expect(number.x).toBeGreaterThanOrEqual(end.x)
      expect(number.x + number.width).toBeLessThanOrEqual(exportButton.x)
      expect(Math.abs(end.y - toolbar.y)).toBeLessThan(2)

      // tabular figures, the small muted type of the end group
      expect(await count(page).evaluate((el) => getComputedStyle(el).fontVariantNumeric)).toContain('tabular-nums')
    })

    test('follows a filter, a search and a clear, without a reload', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw })
      await page.evaluate(() => { window.__marker = 'same document' })

      await page.locator('[data-atom-filter-chip-body]').filter({ hasText: 'Status' }).click()
      await option(page, 'Open').click()
      await expect(count(page)).toHaveText('1–42 of 42')

      await page.locator('[data-atom-filter-chip-body]').filter({ hasText: 'Tag' }).click()
      await option(page, 'Tag A').click()
      // open and tag a: ids that are multiples of 3 and of 4 -> 12, 24, ... 120
      await expect(count(page)).toHaveText('1–10 of 10')
      await page.keyboard.press('Escape')

      await page.getByRole('button', { name: 'Remove filter: Tag' }).click()
      await expect(count(page)).toHaveText('1–42 of 42')
      await page.getByRole('button', { name: 'Remove filter: Status' }).click()
      await expect(count(page)).toHaveText('1–50 of 128')

      await page.getByPlaceholder('Search items').fill('Item 12')
      // Item 12, and 120 to 128
      await expect(count(page)).toHaveText('1–10 of 10')

      expect(await page.evaluate(() => window.__marker)).toBe('same document')
    })

    test('follows the page and the rows per page', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw })

      await page.getByRole('button', { name: 'Go to page 2' }).click()
      await expect(count(page)).toHaveText('51–100 of 128')

      await page.getByRole('button', { name: 'Go to page 3' }).click()
      await expect(count(page)).toHaveText('101–128 of 128')

      // back on page 1 first: page 3 does not exist at 100 rows a page
      await page.getByRole('button', { name: 'Go to page 1' }).click()
      await expect(count(page)).toHaveText('1–50 of 128')
      await page.getByRole('button', { name: /rows\s*\/\s*page/ }).click()
      await page.getByRole('menuitem', { name: '100 rows / page' }).click()
      await expect(count(page)).toHaveText('1–100 of 128')
    })

    test('a round trip that changes nothing leaves it as it was, and no results leaves nothing to say', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw })

      await page.evaluate(() => window.Livewire.find(document.querySelector('[data-testid=bump]').closest('[wire\\:id]').getAttribute('wire:id')).call('bump'))
      await expect(page.getByTestId('bump')).toContainText('Bump 1')
      await expect(count(page)).toHaveText('1–50 of 128')

      await page.getByPlaceholder('Search items').fill('zzz')
      await expect(count(page)).toBeHidden()
      await page.getByPlaceholder('Search items').fill('')
      await expect(count(page)).toHaveText('1–50 of 128')
    })

    test(':count="false" leaves it out', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&count=0' })

      await expect(page.locator('[data-atom-table-toolbar-end]')).toBeVisible()
      await expect(count(page)).toHaveCount(0)
    })

    test('a table with no paginator has no count to show', async ({ page }) => {
      await open(page, { path: 'table-filter-bar', tw, extra: '&plain=1' })

      await expect(page.locator('[data-atom-table-toolbar-end]')).toBeVisible()
      await expect(count(page)).toBeHidden()
      await expect(page.locator('[data-atom-table-count-source]')).toHaveCount(0)
    })

    test('a toolbar outside a table, with nothing at its end, shows no end group at all', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-toolbar-end', tw })

      const bar = page.getByTestId('toolbar-narrow')
      await expect(bar.locator('[data-atom-table-count]')).toBeHidden()
      await expect(page.getByTestId('toolbar-trashed').locator('[data-atom-table-count]')).toBeHidden()
    })

    for (const w of [360, 390]) {
      test(`at ${w}px the count is hidden, so an icon-only end group shares the search's row`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 800 })
        await open(page, { path: 'table-filter-bar', tw, extra: '&iconend=1' })

        // the count is there for the page (a sm-up toolbar shows it) and hidden on a phone, where the pager says the range
        await expect(page.locator('[data-atom-table-count-source]')).toHaveAttribute('data-text', '1–50 of 128')
        await expect(count(page)).toBeHidden()

        const search = await page.locator('[data-atom-table-search]').boundingBox()
        const end = await page.locator('[data-atom-table-toolbar-end]').boundingBox()

        expect(end.x).toBeGreaterThan(search.x + search.width - 1)
        expect(Math.abs((end.y + end.height / 2) - (search.y + search.height / 2))).toBeLessThan(6)
        expect(search.width).toBeGreaterThanOrEqual(160)
      })
    }
  })
}
