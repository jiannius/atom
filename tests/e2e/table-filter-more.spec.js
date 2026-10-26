import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The "More filters" button (beta.10): a pill the size of the add pills (26px, text-xs, a hairline, the surface), a sliders icon
// before the label and a count after it: the number of active filters whose control sits in the slot. In each overflow mode
// (popover, card, modal), on the consumer's Tailwind, floor and latest.

const menuOf = (trigger) => trigger.locator('xpath=ancestor::*[@data-atom-dropdown][1]').locator('[data-atom-menu]')

for (const tw of ['floor', 'latest']) {
  test.describe(`the More filters button, ${tw} consumer build`, () => {
    test('it is a pill like the add pills: the same height, a hairline and the surface, not dashed, a 14px sliders icon before the label', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filters-inline', tw })

      const row = page.getByTestId('inline')
      const addPill = row.locator('[data-atom-select-combobox]').filter({ hasText: 'Customer' })
      const more = page.getByTestId('overflow-popover').locator('[data-atom-table-filters-more-button]')

      const pill = await addPill.boundingBox()
      const button = await more.boundingBox()
      expect(button.height).toBe(26)
      expect(button.height).toBe(pill.height)

      const style = await more.evaluate((el) => {
        const s = getComputedStyle(el)
        const icon = el.querySelector('svg').getBoundingClientRect()
        const text = el.querySelector('span:not([data-atom-icon]):not([data-atom-table-filters-more-count])')

        return {
          fontSize: s.fontSize,
          border: [s.borderTopStyle, s.borderTopWidth],
          radius: parseFloat(s.borderTopLeftRadius) >= 13,
          bg: s.backgroundColor !== 'rgba(0, 0, 0, 0)',
          icon: [icon.width, icon.height],
          // the icon comes before the label
          iconFirst: !!(el.querySelector('svg').compareDocumentPosition(text) & Node.DOCUMENT_POSITION_FOLLOWING),
          label: text.textContent.trim(),
          caret: !!el.querySelector('svg path[d*="M10 3a.75"]'),
        }
      })

      // the add pills' own type size (text-xs)
      expect(style.fontSize).toBe(await addPill.evaluate((el) => getComputedStyle(el).fontSize))
      expect(style.border).toEqual(['solid', '1px'])
      expect(style.radius).toBe(true)
      expect(style.bg).toBe(true)
      expect(style.icon).toEqual([14, 14])
      expect(style.iconFirst).toBe(true)
      expect(style.label).toBe('More filters')
      // the big up-down caret is gone
      expect(style.caret).toBe(false)
    })

    for (const mode of ['popover', 'card', 'modal']) {
      test(`overflow="${mode}": the count follows the filters in the slot, live, and is hidden at 0`, async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 })
        await open(page, { path: 'table-filters-inline', tw })

        const row = page.getByTestId(`overflow-${mode}`)
        const more = row.locator('[data-atom-table-filters-more-button]')
        const count = more.locator('[data-atom-table-filters-more-count]')
        const region = row.locator('[data-atom-table-filters-more] [data-atom-filter-chip-body]').filter({ hasText: 'Region' })
        const zones = row.locator('[data-atom-table-filters-more] [data-atom-filter-chip-body]').filter({ hasText: 'Zones' })

        // the bar's own Status chip is up already, and is not in the slot: it is not counted
        await expect(row.locator('[data-atom-table-filter-chip]')).toHaveCount(1)
        await expect(count).toBeHidden()

        await more.click()
        await region.click()
        await menuOf(region).locator('[data-atom-option]').filter({ hasText: 'North' }).click()
        await expect(count).toBeVisible()
        await expect(count).toHaveText('1')

        await zones.click()
        await menuOf(zones).locator('[data-atom-option]').filter({ hasText: 'Zone 1' }).click()
        await expect(count).toHaveText('2')

        // a second value in the same filter is still one filter (a control in the slot is not the own chip: its list closes on a pick, as it did)
        await zones.click()
        await menuOf(zones).locator('[data-atom-option]').filter({ hasText: 'Zone 2' }).click()
        await expect(count).toHaveText('2')

        // back out of the panel (a popover and a modal are over the bar), then clear them from the bar's chips
        if (mode !== 'card') await page.keyboard.press('Escape')
        if (mode !== 'card') await page.keyboard.press('Escape')

        await row.getByRole('button', { name: 'Remove filter: Region' }).click()
        await expect(count).toHaveText('1')
        await row.getByRole('button', { name: 'Remove filter: Zones' }).click()
        await expect(count).toBeHidden()
        // the Status chip never counted, and is still there
        await expect(row.locator('[data-atom-table-filter-chip]')).toHaveCount(1)
      })
    }

    test('on a Livewire round trip the count follows a filter in the slot too', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filter-bar', tw })

      const more = page.locator('[data-atom-table-filters-more-button]')
      const count = more.locator('[data-atom-table-filters-more-count]')
      const region = page.locator('[data-atom-table-filters-more] [data-atom-filter-chip-body]')

      // a filter outside the slot does not count
      await page.locator('[data-atom-filter-chip-body]').filter({ hasText: 'Status' }).click()
      await page.locator('[data-atom-option]').filter({ hasText: 'Open' }).first().click()
      await expect(count).toBeHidden()

      await more.click()
      await region.click()
      await menuOf(region).locator('[data-atom-option]').filter({ hasText: 'South' }).click()
      await expect(count).toHaveText('1')
    })
  })
}
