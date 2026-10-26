import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The chip is the editor (beta.10): in an `<atom:table.filters chips="inline">` bar, a select filter or a date range with `add` is
// ONE button, the add pill until it holds a value and the chip after, and opens its own menu either way. On a real Livewire round
// trip (tests/Fixtures/TableFilterBarFixture.php) on the consumer's Tailwind, floor and latest. The pill-to-chip look, the "below"
// layout and the "More filters" fall-back are in table-filters-inline.spec.js.

const bar = (page) => page.locator('[data-atom-table-filters]')
const body = (page, label) => bar(page).locator('[data-atom-filter-chip-body]').filter({ hasText: label })
const chipOf = (page, label) => bar(page).locator('[data-atom-table-filter-chip]').filter({ hasText: label })
const option = (page, text) => page.locator('[data-atom-option]').filter({ hasText: text }).first()
const menuOf = (trigger) => trigger.locator('xpath=ancestor::*[@data-atom-dropdown][1]').locator('[data-atom-menu]')
// Escape closes the open list, then the More filters menu; press it until the menu is closed (a press that lands while a list is still opening closes only that)
const closeMore = async (page) => {
  const more = page.locator('[data-atom-table-filters-more]')

  for (let i = 0; i < 4 && await more.evaluate((el) => el.matches(':popover-open')); i++) {
    await page.keyboard.press('Escape')
    await page.waitForTimeout(150)
  }

  await expect.poll(() => more.evaluate((el) => el.matches(':popover-open'))).toBe(false)
}
const text = async (locator) => (await locator.innerText()).replace(/\s+/g, ' ').trim()

for (const tw of ['floor', 'latest']) {
  test.describe(`the chip is the editor, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'table-filter-bar', tw })
    })

    test('a multiple select: the pill is the chip, its menu opens under the chip with the values ticked, and it names two values then counts', async ({ page }) => {
      const tag = body(page, 'Tag')

      await tag.click()
      await option(page, 'Tag A').click()

      // one control: the add pill turned into the chip, and the menu stays open for the next pick
      await expect(chipOf(page, 'Tag A')).toHaveCount(1)
      await expect.poll(() => text(tag)).toBe('Tag is any of Tag A')
      expect(await tag.evaluate((el) => el.hasAttribute('data-chip'))).toBe(true)
      await expect(option(page, 'Tag B')).toBeVisible()

      // close it and open it again from the chip: anchored under the chip body, left edges aligned, Tag A ticked
      await page.keyboard.press('Escape')
      await expect(option(page, 'Tag B')).toBeHidden()
      await tag.click()

      const chip = await tag.boundingBox()
      const menu = await menuOf(tag).boundingBox()
      expect(Math.abs(menu.x - chip.x)).toBeLessThan(2)
      expect(menu.y).toBeGreaterThanOrEqual(chip.y + chip.height)
      expect(menu.y - (chip.y + chip.height)).toBeLessThan(16)
      await expect(option(page, 'Tag A')).toHaveAttribute('aria-selected', 'true')
      await expect(option(page, 'Tag B')).toHaveAttribute('aria-selected', 'false')

      await option(page, 'Tag B').click()
      await expect.poll(() => text(tag)).toBe('Tag is any of Tag A, Tag B')
      await option(page, 'Tag C').click()
      await expect.poll(() => text(tag)).toBe('Tag is any of 3 selected')

      // the menu is still open, and picking a ticked one takes it off
      await expect(option(page, 'Tag D')).toBeVisible()
      await option(page, 'Tag C').click()
      await expect.poll(() => text(tag)).toBe('Tag is any of Tag A, Tag B')
    })

    test('the x clears the filter and the add pill comes back with the focus on it', async ({ page }) => {
      const tag = body(page, 'Tag')

      await tag.click()
      await option(page, 'Tag A').click()
      await option(page, 'Tag B').click()
      await page.keyboard.press('Escape')
      await expect(chipOf(page, 'Tag A')).toHaveCount(1)

      await bar(page).getByRole('button', { name: 'Remove filter: Tag' }).click()

      await expect(chipOf(page, 'Tag')).toHaveCount(0)
      await expect(tag).toBeFocused()
      expect(await tag.evaluate((el) => [getComputedStyle(el).borderTopStyle, el.hasAttribute('data-chip')])).toEqual(['dashed', false])
      expect(await text(tag)).toBe('Tag')
      // the rows are back to everything
      await expect(page.locator('[data-atom-table-rows] tr')).toHaveCount(50)
    })

    test('a single select closes on a pick; the chip reopens it with the value selected, and a pick replaces it', async ({ page }) => {
      const status = body(page, 'Status')

      await status.click()
      await option(page, 'Open').click()

      await expect(option(page, 'Closed')).toBeHidden()
      await expect.poll(() => text(status)).toBe('Status is Open')
      expect(await status.evaluate((el) => el.hasAttribute('data-chip'))).toBe(true)

      await status.click()
      await expect(option(page, 'Open')).toHaveAttribute('aria-selected', 'true')
      await option(page, 'Closed').click()

      await expect(option(page, 'Open')).toBeHidden()
      await expect.poll(() => text(status)).toBe('Status is Closed')
      // one chip for the key, not two
      await expect(chipOf(page, 'Status')).toHaveCount(1)
    })

    test('a date range: the chip opens the range panel with the range shown, and a new pick changes it', async ({ page }) => {
      const issued = body(page, 'Issued')

      await issued.click()
      await page.getByRole('menuitem', { name: 'Last 7 Days' }).click()
      await expect(chipOf(page, 'Issued')).toHaveCount(1)
      const before = await text(issued)
      expect(before).toMatch(/^Issued is \d{2} \w{3} \d{4} to \d{2} \w{3} \d{4}$/)

      // locked, as today: the panel stays up after a preset, until the page is clicked elsewhere
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeHidden()

      await issued.click()
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeVisible()
      await expect(menuOf(issued).locator('.is-startrange').first()).toBeVisible()
      await expect(menuOf(issued).locator('.is-endrange').first()).toBeVisible()

      await page.getByRole('menuitem', { name: 'Today' }).click()
      await expect.poll(() => text(issued)).not.toBe(before)
      await expect.poll(() => text(issued)).toMatch(/^Issued is (\d{2} \w{3} \d{4}) to \1$/)
    })

    test('the keyboard: Enter or Space opens the chip, Escape closes it with the focus back on it, Tab goes body then x, Enter on the x clears', async ({ page }) => {
      const status = body(page, 'Status')

      await status.focus()
      await page.keyboard.press('Enter')
      await expect(option(page, 'Open')).toBeVisible()
      // the list resets its active option a tick after it opens: let that land before moving
      await expect(status).toHaveAttribute('aria-expanded', 'true')
      await page.waitForTimeout(150)
      await page.keyboard.press('ArrowDown')
      await expect(option(page, 'Open')).toHaveAttribute('data-active', '')
      await page.keyboard.press('Enter')
      await expect.poll(() => text(status)).toBe('Status is Open')
      await expect(option(page, 'Closed')).toBeHidden()

      // a chip opens on Enter and Space, and Escape gives the focus back to it
      await status.focus()
      await page.keyboard.press('Enter')
      await expect(option(page, 'Closed')).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(option(page, 'Closed')).toBeHidden()
      await expect(status).toBeFocused()

      await page.keyboard.press('Space')
      await expect(option(page, 'Closed')).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(status).toBeFocused()

      // tab order: the chip body, then its x
      await page.keyboard.press('Tab')
      await expect(bar(page).getByRole('button', { name: 'Remove filter: Status' })).toBeFocused()

      // Enter on the x clears (it must not open the list), and the focus lands on the pill that comes back
      await page.keyboard.press('Enter')
      await expect(chipOf(page, 'Status')).toHaveCount(0)
      await expect(status).toBeFocused()
      await expect(option(page, 'Closed')).toBeHidden()
    })

    test('a date range chip opens from the keyboard and Escape gives the focus back', async ({ page }) => {
      const issued = body(page, 'Issued')

      await issued.focus()
      await page.keyboard.press('Enter')
      await page.getByRole('menuitem', { name: 'Last 7 Days' }).click()
      await page.keyboard.press('Escape')
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeHidden()

      await issued.focus()
      await page.keyboard.press('Enter')
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(page.getByRole('menuitem', { name: 'Today' })).toBeHidden()
      await expect(issued).toBeFocused()
      await page.keyboard.press('Tab')
      await expect(bar(page).getByRole('button', { name: 'Remove filter: Issued' })).toBeFocused()
    })

    test('a morph while a chip menu is open keeps the menu open, the chip and its place in the row', async ({ page }) => {
      const tag = body(page, 'Tag')

      await tag.click()
      await option(page, 'Tag A').click()
      await option(page, 'Tag B').click()
      await expect.poll(() => text(tag)).toBe('Tag is any of Tag A, Tag B')

      const before = await tag.boundingBox()

      // a round trip that renders the same table: the morph lands while the popover is open (a click on the page would close it)
      await page.evaluate(() => window.Livewire.find(document.querySelector('[data-testid=bump]').closest('[wire\\:id]').getAttribute('wire:id')).call('bump'))
      await expect(page.getByTestId('bump')).toContainText('Bump 1')

      await expect(option(page, 'Tag C')).toBeVisible()
      expect(await menuOf(tag).evaluate((el) => el.matches(':popover-open'))).toBe(true)
      await expect(tag).toHaveAttribute('aria-expanded', 'true')
      expect(await text(tag)).toBe('Tag is any of Tag A, Tag B')
      // still the first thing in the row after the search: the order attribute survived the morph, and the menu did not jump
      expect(await tag.evaluate((el) => el.closest('[data-atom-select-combobox]') !== null && el.hasAttribute('data-chip'))).toBe(true)
      expect(await tag.locator('xpath=ancestor::*[@data-atom-filter-active][1]').count()).toBe(1)
      const after = await tag.boundingBox()
      const menu = await menuOf(tag).boundingBox()
      expect(Math.abs(after.x - before.x)).toBeLessThan(1)
      expect(Math.abs(menu.x - after.x)).toBeLessThan(2)

      // and a pick still works after it
      await option(page, 'Tag C').click()
      await expect.poll(() => text(tag)).toBe('Tag is any of 3 selected')
    })

    test('a morph while a filter holds a chip leaves it a chip: the state is the control\'s, not the markup\'s', async ({ page }) => {
      const status = body(page, 'Status')

      await status.click()
      await option(page, 'Hold').or(option(page, 'On hold')).first().click()
      await expect.poll(() => text(status)).toBe('Status is On hold')

      await page.evaluate(() => window.Livewire.find(document.querySelector('[data-testid=bump]').closest('[wire\\:id]').getAttribute('wire:id')).call('bump'))
      await expect(page.getByTestId('bump')).toContainText('Bump 1')

      expect(await text(status)).toBe('Status is On hold')
      expect(await status.evaluate((el) => el.hasAttribute('data-chip'))).toBe(true)
      expect(await status.locator('xpath=ancestor::*[@data-atom-filter-active][1]').count()).toBe(1)
      await expect(bar(page).getByRole('button', { name: 'Remove filter: Status' })).toBeVisible()
    })

    test('a control in the More filters slot is not drawn as a chip in the bar: the bar draws its chip', async ({ page }) => {
      await bar(page).getByRole('button', { name: 'More filters' }).click()
      const region = bar(page).locator('[data-atom-table-filters-more] [data-atom-filter-chip-body]')

      await region.click()
      await option(page, 'North').click()

      // the bar's own chip (outside the slot, in the row), the add control stays the add control
      await expect(bar(page).locator(':scope > [data-atom-table-filter-chip]').filter({ hasText: 'Region' })).toHaveCount(1)
      expect(await region.evaluate((el) => el.hasAttribute('data-chip'))).toBe(false)
    })

    // A chip's x or Clear all removes the thing the focus was on: it must not fall to <body>.
    test('a bar-drawn chip\'s x: the focus goes to the next chip, and with none left, to the search', async ({ page }) => {
      const search = page.getByPlaceholder('Search items')

      // Region lives in the More filters slot, so the bar draws its chip (and its control is out of sight in the closed menu)
      await body(page, 'Status').click()
      await option(page, 'Open').click()
      await bar(page).getByRole('button', { name: 'More filters' }).click()
      const region = bar(page).locator('[data-atom-table-filters-more] [data-atom-filter-chip-body]')
      await region.click()
      await menuOf(region).locator('[data-atom-option]').filter({ hasText: 'North' }).click()
      await closeMore(page)

      const regionChip = bar(page).locator(':scope > [data-atom-table-filter-chip]').filter({ hasText: 'Region' })
      await expect(regionChip).toHaveCount(1)

      await regionChip.getByRole('button', { name: 'Remove filter: Region' }).click()
      await expect(regionChip).toHaveCount(0)
      // the next chip: Status's own, whose body takes the focus
      await expect(body(page, 'Status')).toBeFocused()

      // clear the last one (its own x puts the focus on the pill it turns back into), then Region again alone
      await bar(page).getByRole('button', { name: 'Remove filter: Status' }).click()
      await expect(body(page, 'Status')).toBeFocused()

      await bar(page).getByRole('button', { name: 'More filters' }).click()
      await region.click()
      await menuOf(region).locator('[data-atom-option]').filter({ hasText: 'South' }).click()
      await closeMore(page)
      await regionChip.getByRole('button', { name: 'Remove filter: Region' }).click()
      await expect(regionChip).toHaveCount(0)
      await expect(search).toBeFocused()
    })

    test('Clear all: the focus goes to the first control\'s pill', async ({ page }) => {
      await body(page, 'Tag').click()
      await option(page, 'Tag A').click()
      await page.keyboard.press('Escape')
      await body(page, 'Status').click()
      await option(page, 'Open').click()

      await bar(page).getByRole('button', { name: 'Clear all' }).click()

      await expect(chipOf(page, 'Status')).toHaveCount(0)
      await expect(body(page, 'Status')).toBeFocused()
      expect(await body(page, 'Status').evaluate((el) => el.hasAttribute('data-chip'))).toBe(false)
    })

    test('Clear all with only the search active: the focus goes to the search', async ({ page }) => {
      const search = page.getByPlaceholder('Search items')

      await search.fill('Item 12')
      await expect(page.locator('[data-atom-table-count]')).toHaveText('1–10 of 10')
      await bar(page).getByRole('button', { name: 'Clear all' }).click()

      await expect(search).toHaveValue('')
      // the first control's pill is there, so that is where it goes; with none showing it would be the search
      await expect(body(page, 'Status')).toBeFocused()
    })

    test('an inline filter that is not clearable still has its x on the chip', async ({ page }) => {
      await open(page, { path: 'table-filters-inline', tw })
      const pill = page.getByTestId('inline').locator('[data-atom-select-combobox]').filter({ hasText: 'Priority' })

      await pill.click()
      await page.locator('[data-atom-option]').filter({ hasText: 'High' }).first().click()
      await expect(pill).toHaveAttribute('data-chip', '')
      await page.getByTestId('inline').getByRole('button', { name: 'Remove filter: Priority' }).click()
      await expect(pill).not.toHaveAttribute('data-chip', '')
      await expect(pill).toBeFocused()
    })
  })
}
