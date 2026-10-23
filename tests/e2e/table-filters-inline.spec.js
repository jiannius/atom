import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { sweepText, ACCENTS, TEXT } from './display-helpers.js'

// <atom:table.filters chips="inline"> and the add-filter pill (`add` on a select filter or a date range), on the consumer's
// Tailwind (`node tests/consumer/build.mjs`), floor and latest: the chip joins the controls' row as a sentence pill, the dashed pill steps
// aside for it and comes back when the chip's x is pressed; every text role reads in both themes.

const bar = (page) => page.locator('[data-testid="inline"] [data-atom-table-filters]')
const chips = (page) => bar(page).locator('[data-atom-table-filter-chip]')

for (const tw of ['floor', 'latest']) {
  test.describe(`inline filter pills, ${tw} consumer build`, () => {
    test('the preset filter is a sentence pill in the same row as the search and the add pills', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filters-inline', tw })

      await expect(chips(page)).toHaveCount(1)
      await expect(chips(page).first()).toContainText('Status')
      await expect(chips(page).first()).toContainText('is')
      await expect(chips(page).first()).toContainText('Overdue')

      const centre = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { y: r.y + r.height / 2, x: r.x, right: r.right } })
      const search = await centre(page.locator('[data-testid="inline"] [data-atom-table-search]'))
      const pill = await centre(page.getByRole('combobox', { name: 'Customer' }))
      const chip = await centre(chips(page).first())

      // one row: the pills and the chip are centred on the search box, and the chip comes after the controls
      expect(Math.abs(pill.y - search.y)).toBeLessThan(3)
      expect(Math.abs(chip.y - search.y)).toBeLessThan(3)
      expect(chip.x).toBeGreaterThan(pill.right)
    })

    test('the add pill is dashed, and steps aside for its chip, which brings it back when removed', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByRole('combobox', { name: 'Customer' })

      expect(await pill.evaluate((el) => getComputedStyle(el).borderTopStyle)).toBe('dashed')
      await expect(pill).toContainText('Customer')

      await pill.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Customer B' }).first().click()

      await expect(chips(page).filter({ hasText: 'Customer B' })).toHaveCount(1)
      await expect(chips(page).filter({ hasText: 'Customer B' })).toContainText('Customer')
      await expect(pill).toBeHidden()

      await bar(page).getByRole('button', { name: 'Remove filter: Customer' }).click()

      await expect(chips(page).filter({ hasText: 'Customer B' })).toHaveCount(0)
      await expect(pill).toBeVisible()
    })

    test('a date range: set one from the calendar, the chip takes the pill\'s place, and its x brings the pill back', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByRole('button', { name: 'Issued', exact: true })

      expect(await pill.evaluate((el) => [getComputedStyle(el).borderTopStyle, el.tagName])).toEqual(['dashed', 'BUTTON'])
      await expect(pill).toContainText('Issued')

      await pill.click()
      await page.getByRole('menuitem', { name: 'Last 7 Days' }).click()

      const chip = chips(page).filter({ hasText: 'Issued' })

      await expect(chip).toHaveCount(1)
      await expect(chip).toContainText('is')
      await expect(pill).toBeHidden()

      await chip.getByRole('button', { name: 'Remove filter: Issued' }).click()

      await expect(chips(page).filter({ hasText: 'Issued' })).toHaveCount(0)
      await expect(pill).toBeVisible()
    })

    test('a multiple add pill stays, to add more values', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByRole('combobox', { name: 'Tag' })
      await pill.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Tag X' }).first().click()

      await expect(chips(page).filter({ hasText: 'Tag X' })).toHaveCount(1)
      await expect(pill).toBeVisible()
    })

    test('the search reads as "Search: foo" and Clear all empties every chip', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const input = page.locator('[data-testid="inline"] [data-atom-table-search] input')
      await input.fill('acme')
      await input.press('Enter')

      const chip = chips(page).filter({ hasText: 'acme' })
      await expect(chip).toHaveCount(1)
      await expect(chip).toContainText(/Search number or customer\s*:/)

      await bar(page).getByRole('button', { name: 'Clear all' }).click()
      await expect(chips(page)).toHaveCount(0)
      await expect(bar(page).getByRole('button', { name: 'Clear all' })).toBeHidden()
    })

    for (const dark of [false, true]) {
      for (const accent of [null, ...ACCENTS]) {
        test(`every text reads in ${dark ? 'dark' : 'light'} with ${accent ?? 'the default accent'}`, async ({ page }) => {
          await open(page, { path: 'table-filters-inline', tw, dark, accent })
          await expect(chips(page)).toHaveCount(1)

          expect(await sweepText(page, '[data-testid="inline"]', TEXT, 4)).toEqual([])
        })
      }
    }
  })
}
