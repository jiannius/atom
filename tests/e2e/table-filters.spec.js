import { test, expect } from '@playwright/test'

// Use the minimal fixture page /atom/e2e/table-filters which renders just the two
// filter bars near the top of the page. This keeps FloatingUI's dropdown positioning
// within the viewport — the full /atom/docs/table page is too long (12k+ px) and the
// filter demo sits far enough down that the native popover, positioned by FloatingUI,
// ends up outside Playwright's viewport bounds on every standard viewport size.

test('selecting a filter shows a chip; clearing it removes the chip', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').first()

  // open the Status filter (the select trigger is role=combobox since the v3.5.19 ARIA
  // rewrite; its label is child text, not an accessible name, so match by text)
  await bar.getByRole('combobox').filter({ hasText: 'Status' }).first().click()

  // click the Published option (rendered by Alpine x-for into the dropdown on open)
  await page.locator('[data-atom-option]').filter({ hasText: 'Published' }).first().click()

  // KEY ASSERTION: a chip appears in the bar showing "Published".
  // This proves: wire:model → $filterKey set → $watch('selectValue') fires →
  // $dispatch('table-filter:set') dispatched → bar's x-on:table-filter:set.window
  // handler called → chip auto-register worked end-to-end.
  const chip = bar.locator('div.inline-flex.items-center').filter({ hasText: 'Published' }).first()
  await expect(chip).toBeVisible()
  await expect(chip).toContainText('Published')

  // clear via the chip ✕ button
  await chip.getByRole('button').click()

  // chip is gone
  await expect(bar.locator('div.inline-flex.items-center').filter({ hasText: 'Published' })).toHaveCount(0)
})

test('Clear all removes every chip', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').first()

  // select a value to create a chip
  await bar.getByRole('combobox').filter({ hasText: 'Status' }).first().click()
  await page.locator('[data-atom-option]').filter({ hasText: 'Published' }).first().click()

  // "Clear all" button appears once chips are active
  const clearAll = bar.getByRole('button', { name: /Clear all/i })
  await expect(clearAll).toBeVisible()

  // clicking it removes all chips
  await clearAll.click()
  await expect(bar.locator('div.inline-flex.items-center').filter({ hasText: 'Published' })).toHaveCount(0)
  await expect(clearAll).toHaveCount(0)
})
