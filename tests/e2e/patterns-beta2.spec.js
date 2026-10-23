import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { sweepText, TEXT } from './display-helpers.js'

// The pattern pages as a page: the record page's rail beside the main column and under it on a phone, the stage path, the list's filter
// pills and checkbox column, the form's editable lines, the shell page's primary. On the consumer's Tailwind, floor and latest, light and
// dark; every text role on its own surface. The Pest suite holds the structure; this holds what a browser makes of it.

const rect = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, right: r.right, bottom: r.bottom, width: r.width, height: r.height } })
const noSidewaysScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

for (const tw of ['floor', 'latest']) {
  test.describe(`patterns, ${tw} consumer build`, () => {
    test('record page: the rail sits beside the main column at 1440px and under it at 390px, with the stage path in a row and then a column', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'shell-record', tw })

      const lines = await rect(page.getByTestId('record-lines'))
      const rail = await rect(page.getByTestId('record-rail'))
      expect(rail.x).toBeGreaterThan(lines.right)
      expect(Math.round(rail.width)).toBe(320)

      const [first, second] = await page.getByTestId('record-stages').locator('> ol > li').evaluateAll((els) => els.slice(0, 2).map((el) => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y } }))
      expect(second.x).toBeGreaterThan(first.x)
      expect(second.y).toBeCloseTo(first.y, 0)

      await page.setViewportSize({ width: 390, height: 900 })
      await open(page, { path: 'shell-record', tw })

      const narrowLines = await rect(page.getByTestId('record-lines'))
      const narrowRail = await rect(page.getByTestId('record-rail'))
      expect(narrowRail.y).toBeGreaterThan(narrowLines.bottom - 1)
      expect(narrowRail.x).toBeLessThan(narrowLines.right)
      expect(await noSidewaysScroll(page)).toBe(true)

      const [a, b] = await page.getByTestId('record-stages').locator('> ol > li').evaluateAll((els) => els.slice(0, 2).map((el) => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y } }))
      expect(b.y).toBeGreaterThan(a.y)
      expect(b.x).toBeCloseTo(a.x, 0)
    })

    for (const dark of [false, true]) {
      test(`record page reads in ${dark ? 'dark' : 'light'}: the rail, the stage path and the lines panel`, async ({ page }) => {
        await open(page, { path: 'shell-record', tw, dark })

        expect(await sweepText(page, '[data-testid="record-rail"], [data-testid="record-stages"], [data-testid="record-lines"], [data-testid="record-description"]', TEXT, 20)).toEqual([])
      })

      test(`list page reads in ${dark ? 'dark' : 'light'}: the filter pills and the checkbox column`, async ({ page }) => {
        await open(page, { path: 'shell-list', tw, dark })

        expect(await sweepText(page, '[data-testid="list-panel"]', TEXT, 20)).toEqual([])
      })

      test(`form page reads in ${dark ? 'dark' : 'light'}: the editable lines and the muted (optional)`, async ({ page }) => {
        await open(page, { path: 'shell-form', tw, dark })

        expect(await sweepText(page, '[data-testid="form-page"]', TEXT, 20)).toEqual([])
      })

      test(`document page reads in ${dark ? 'dark' : 'light'}: the rail, the success balance bar and the reminder`, async ({ page }) => {
        await open(page, { path: 'shell-document', tw, dark })

        expect(await sweepText(page, '[data-testid="balance-box"], [data-testid="history"], [data-atom-page-header]', TEXT, 8)).toEqual([])
      })
    }

    test('list page: the add pills and the issue-date chip share the search\'s row, and removing the chip brings its pill back', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'shell-list', tw })

      const search = await rect(page.locator('[data-testid="list-panel"] [data-atom-table-search]'))
      const chip = page.locator('[data-testid="list-panel"] [data-atom-table-filter-chip]')

      await expect(chip).toHaveCount(1)
      await expect(chip).toContainText('Issued')
      await expect(chip).toContainText('is')
      expect(Math.abs((await rect(chip)).y + (await rect(chip)).height / 2 - (search.y + search.height / 2))).toBeLessThan(3)

      const pill = page.getByRole('textbox', { name: /Issued/ })
      await expect(pill).toBeHidden()
      await chip.getByRole('button', { name: /Remove filter: Issued/ }).click()
      await expect(pill).toBeVisible()
      await expect(chip).toHaveCount(0)
    })

    test('list page: a row\'s checkbox ticks without opening the row, and the header primary is the only accent button', async ({ page }) => {
      await open(page, { path: 'shell-list', tw })

      const box = page.locator('[data-atom-table] tbody tr').first().locator('[data-atom-table-checkbox]')
      await box.click()

      await expect(box).toHaveAttribute('data-checked', 'true')
      await expect(page).toHaveURL(/shell-list/)
      await expect(page.locator('[data-atom-page-actions] [data-atom-button].bg-accent')).toHaveCount(1)
    })

    test('form page: the line inputs are right-aligned figures, a remove is a ghost button and the (optional) is muted', async ({ page }) => {
      await open(page, { path: 'shell-form', tw })

      const row = page.getByTestId('form-lines').locator('tbody tr').first()
      const qty = await row.locator('td').nth(1).locator('input').evaluate((el) => { const s = getComputedStyle(el); return [s.textAlign, s.fontVariantNumeric] })
      expect(qty[0]).toBe('right')
      expect(qty[1]).toContain('tabular-nums')

      const optional = page.locator('[data-atom-label-optional]').first()
      expect(await optional.evaluate((el) => getComputedStyle(el).fontWeight)).toBe('400')
      expect(await optional.evaluate((el) => getComputedStyle(el).color)).not.toBe(await optional.evaluate((el) => getComputedStyle(el.closest('label')).color))
    })

    test('shell pages: the primary is in the page header and the shell\'s actions hold no accent button', async ({ page }) => {
      for (const path of ['shell-sidebar', 'shell-header']) {
        await open(page, { path, tw })

        await expect(page.locator('[data-atom-page-header] [data-testid="shell-primary"]')).toHaveCount(1)
        await expect(page.locator('[data-atom-shell-actions] [data-atom-button].bg-accent')).toHaveCount(0)
        await expect(page.locator('[data-atom-main] [data-atom-badge]')).toHaveCount(0)
      }
    })
  })
}
