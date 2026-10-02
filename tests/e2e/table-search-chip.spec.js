import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/table-search-chip
// (tests/Fixtures/TableSearchChipFixture.php).
//
// A bound <atom:table.search> inside <atom:table.filters> used to be invisible to
// the bar: no chip for the text, and "Clear all" cleared the selects but left the
// search box (and its rows) alone. The Livewire round trip is the point — the
// search model is deferred, so the clear has to reach server state.

const bar = page => page.locator('[data-atom-table-filters]')
const box = page => page.locator('[data-atom-table-search] input')
const rows = page => page.locator('[data-atom-table-row]')
const chips = page => page.locator('[data-atom-table-filter-chip]')
const searchChip = page => chips(page).filter({ hasText: 'Search fruit' })

async function search (page, term) {
  await box(page).fill(term)
  await box(page).press('Enter')
}

async function pickStatus (page, label) {
  await bar(page).getByRole('combobox', { name: 'Status' }).click()
  await page.locator('[data-atom-option]').filter({ hasText: label }).first().click()
}

test('Enter on a search shows a chip and filters the rows', async ({ page }) => {
  await page.goto('/atom/e2e/table-search-chip')
  await page.waitForLoadState('networkidle')

  await expect(rows(page)).toHaveCount(6)
  await expect(chips(page)).toHaveCount(0)

  await search(page, 'ap')

  await expect(searchChip(page)).toBeVisible()
  await expect(searchChip(page)).toContainText('ap')
  await expect(rows(page)).toHaveCount(2)
})

test('Clear all empties the search box, removes every chip and unfilters the rows', async ({ page }) => {
  await page.goto('/atom/e2e/table-search-chip')
  await page.waitForLoadState('networkidle')

  await search(page, 'ap')
  await expect(rows(page)).toHaveCount(2)
  await pickStatus(page, 'Fresh')
  await expect(rows(page)).toHaveCount(1)
  await expect(chips(page)).toHaveCount(2)

  await bar(page).getByRole('button', { name: /Clear all/i }).click()

  // KEY ASSERTIONS: the box is empty, no chip is left, and the server state followed —
  // all six rows are back, which only happens if the search model was emptied and sent.
  await expect(box(page)).toHaveValue('')
  await expect(chips(page)).toHaveCount(0)
  await expect(rows(page)).toHaveCount(6)
})

test("the search chip's own x clears the search and leaves the other filter", async ({ page }) => {
  await page.goto('/atom/e2e/table-search-chip')
  await page.waitForLoadState('networkidle')

  await search(page, 'b')
  await pickStatus(page, 'Fresh')
  await expect(rows(page)).toHaveCount(1)

  await searchChip(page).getByRole('button').click()

  await expect(box(page)).toHaveValue('')
  await expect(searchChip(page)).toHaveCount(0)
  // Status: Fresh is still applied
  await expect(chips(page).filter({ hasText: 'Fresh' })).toHaveCount(1)
  await expect(rows(page)).toHaveCount(3)
})

test('leaving the box with text charts it, so the chip matches what the next request sends', async ({ page }) => {
  await page.goto('/atom/e2e/table-search-chip')
  await page.waitForLoadState('networkidle')

  await box(page).fill('cher')
  await page.locator('body').click({ position: { x: 5, y: 400 } })

  await expect(searchChip(page)).toBeVisible()
  await expect(searchChip(page)).toContainText('cher')
  // blur alone does not run the search: the rows wait for Enter or another request
  await expect(rows(page)).toHaveCount(6)

  // emptying the box removes the chip again
  await box(page).fill('')
  await page.locator('body').click({ position: { x: 5, y: 400 } })
  await expect(searchChip(page)).toHaveCount(0)
})
