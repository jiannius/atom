import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { sweepText, ACCENTS, TEXT } from './display-helpers.js'

// <atom:table.filters chips="inline"> and the add-filter pill (`add` on a select filter or a date range), on the consumer's
// Tailwind (`node tests/consumer/build.mjs`), floor and latest: the chip joins the controls' row as a sentence pill, and is the
// dashed pill itself (one button, which opens the filter's menu) until the chip's x is pressed; every text role reads in both themes.
// The chip as an editor (menus, keyboard, a morph) is table-filter-chip.spec.js.

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
      const pill = await centre(page.getByTestId('inline').getByRole('combobox', { name: 'Customer' }))
      const chip = await centre(chips(page).first())

      // one row: the pills and the chip are centred on the search box, and the active chip LEADS the dashed pills
      expect(Math.abs(pill.y - search.y)).toBeLessThan(3)
      expect(Math.abs(chip.y - search.y)).toBeLessThan(3)
      expect(chip.right).toBeLessThan(pill.x)
      expect(chip.x).toBeGreaterThan(search.right)
    })

    test('a search INSIDE the filters keeps the first place: the chips and the pills come after it', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filters-inline', tw })

      const inside = page.getByTestId('inline-inside')
      const search = await inside.locator('[data-atom-table-search]').boundingBox()
      const chip = await inside.locator('[data-atom-table-filter-chip]').first().boundingBox()
      const pill = await inside.getByRole('combobox', { name: 'Region' }).boundingBox()

      await expect(inside.locator('[data-atom-table-filter-chip]').first()).toContainText('Zone')
      expect(chip.x).toBeGreaterThan(search.x + search.width)
      expect(pill.x).toBeGreaterThan(chip.x + chip.width)
      // in one row
      expect(Math.abs((chip.y + chip.height / 2) - (search.y + search.height / 2))).toBeLessThan(4)
    })

    test('the add pill is dashed, and is its own chip once a value is set: one button, with an x that turns it back', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const trigger = page.getByTestId('inline').locator('[data-atom-select-combobox]').filter({ hasText: 'Customer' })
      const pill = page.getByTestId('inline').getByRole('combobox', { name: 'Customer' })

      expect(await pill.evaluate((el) => getComputedStyle(el).borderTopStyle)).toBe('dashed')
      await expect(pill).toContainText('Customer')

      await pill.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Customer B' }).first().click()

      // the chip IS the pill's button (it steps aside by turning into the chip), and nothing else on screen is a Customer pill
      await expect(chips(page).filter({ hasText: 'Customer B' })).toHaveCount(1)
      await expect(chips(page).filter({ hasText: 'Customer B' })).toContainText('Customer')
      await expect(trigger).toHaveCount(1)
      await expect(trigger).toBeVisible()
      // the dashed outline is gone (its colour is transparent: the border keeps the 26px) and the neutral fill is in
      expect(await trigger.evaluate((el) => [getComputedStyle(el).borderTopColor, getComputedStyle(el).backgroundColor !== 'rgba(0, 0, 0, 0)', el.hasAttribute('data-chip')])).toEqual(['rgba(0, 0, 0, 0)', true, true])

      await bar(page).getByRole('button', { name: 'Remove filter: Customer' }).click()

      await expect(chips(page).filter({ hasText: 'Customer B' })).toHaveCount(0)
      await expect(trigger).toBeFocused()
      expect(await trigger.evaluate((el) => [getComputedStyle(el).borderTopStyle, el.hasAttribute('data-chip')])).toEqual(['dashed', false])
    })

    test('a date range: set one from the calendar, the same button becomes the chip, and its x turns it back', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByTestId('inline').locator('[data-atom-date-range-add]')

      expect(await pill.evaluate((el) => [getComputedStyle(el).borderTopStyle, el.tagName])).toEqual(['dashed', 'BUTTON'])
      await expect(pill).toContainText('Issued')

      await pill.click()
      await page.getByRole('menuitem', { name: 'Last 7 Days' }).click()

      const chip = chips(page).filter({ hasText: 'Issued' })

      await expect(chip).toHaveCount(1)
      await expect(chip).toContainText('is')
      await expect(pill).toBeVisible()
      expect(await pill.evaluate((el) => el.hasAttribute('data-chip'))).toBe(true)

      await chip.getByRole('button', { name: 'Remove filter: Issued' }).click()

      await expect(chips(page).filter({ hasText: 'Issued' })).toHaveCount(0)
      await expect(pill).toBeFocused()
      expect(await pill.evaluate((el) => [getComputedStyle(el).borderTopStyle, el.hasAttribute('data-chip')])).toEqual(['dashed', false])
    })

    test('a date range pill opens from the keyboard: Enter, then a preset by key, then Escape closes it', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByRole('button', { name: 'Issued', exact: true })
      const preset = page.getByRole('menuitem', { name: 'Last 7 Days' })

      await pill.focus()
      await page.keyboard.press('Enter')
      await expect(preset).toBeVisible()

      await page.keyboard.press('Escape')
      await expect(preset).toBeHidden()

      // Space opens it too, as a button does
      await pill.focus()
      await page.keyboard.press('Space')
      await expect(preset).toBeVisible()
    })

    test('a multiple add pill is its own chip too, and one control: no second pill beside it', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const pill = page.getByTestId('inline').getByRole('combobox', { name: 'Tag' })
      await pill.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Tag X' }).first().click()

      await expect(chips(page).filter({ hasText: 'Tag X' })).toHaveCount(1)
      await expect(chips(page).filter({ hasText: 'Tag X' })).toContainText('is any of')
      await expect(page.getByTestId('inline').locator('[data-atom-select-combobox]').filter({ hasText: 'Tag' })).toHaveCount(1)
    })

    test('chips="below" is as it was: the bar draws the chip on its own row, a multiple pill stays, and counts from two', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })

      const below = page.getByTestId('below')
      const single = below.getByRole('combobox', { name: 'Kind' })
      const multi = below.getByRole('combobox', { name: 'Group' })

      await single.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Kind One' }).first().click()

      // the pill steps aside (the chip is the bar's, below), and no control marks itself as holding a chip
      await expect(single).toBeHidden()
      await expect(below.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Kind One' })).toHaveCount(1)
      expect(await below.locator('[data-atom-filter-active], [data-chip]').count()).toBe(0)

      await multi.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Group One' }).first().click()

      // a multiple select closes on a pick here, as it always did, and its pill stays
      await expect(page.locator('[data-atom-option]').filter({ hasText: 'Group Two' })).toBeHidden()
      await expect(multi).toBeVisible()
      await multi.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Group Two' }).first().click()

      await expect(below.locator('[data-atom-table-filter-chip]').filter({ hasText: '2 selected' })).toHaveCount(1)
      await expect(below.locator('[data-atom-table-filter-chip]').filter({ hasText: 'is any of' })).toHaveCount(0)
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

    // "More filters" is a filter control like the rest: with chips="inline" it sits AFTER the active chips, in every overflow mode
    // (the modal's button sits inside the display:contents modal trigger, so it used to keep order 0 and lead the row)
    for (const mode of ['card', 'modal', 'popover']) {
      test(`overflow="${mode}": the More filters button comes after the active chip, on one line`, async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 })
        await open(page, { path: 'table-filters-inline', tw })

        const row = page.getByTestId(`overflow-${mode}`)
        const chip = row.locator('[data-atom-table-filter-chip]')
        await expect(chip).toHaveCount(1)

        const more = await row.getByRole('button', { name: 'More filters' }).first().boundingBox()
        const last = await chip.last().boundingBox()

        expect(more.x).toBeGreaterThan(last.x + last.width)
        expect(Math.abs((more.y + more.height / 2) - (last.y + last.height / 2))).toBeLessThan(4)
      })
    }

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
