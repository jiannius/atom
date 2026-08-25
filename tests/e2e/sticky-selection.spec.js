import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/sticky-selection
// (tests/Fixtures/StickySelectionFixture.php).
//
// <atom:table :sticky-selection> exists for the "build one batch across several
// searches" flow: tick 2, search, tick 1 more, act on all 3. Without it a filter
// change clears the selection, because the ticked rows may no longer be on screen.
//
// The Livewire round-trip is the point — the ids live in server state, so this
// only proves anything in a real browser against a real component.

const rowCheckbox = (page, name) =>
  page.locator('[data-atom-table-row]', { has: page.locator(`[data-name="${name}"]`) })
      .locator('[data-atom-table-checkbox]')

// the icon is a <span> too, so match the x-text one that carries the count
const selectedCount = page => page.locator('[data-atom-table-checked] span[x-text]').first()

async function search (page, term) {
  const box = page.locator('[data-atom-table-search] input')
  await box.fill(term)
  await box.press('Enter')
}

test('a selection survives a search, and later ticks add to it', async ({ page }) => {
  await page.goto('/atom/e2e/sticky-selection')
  await page.waitForLoadState('networkidle')

  // tick two rows on the unfiltered list
  await rowCheckbox(page, 'Apple').click()
  await rowCheckbox(page, 'Banana').click()
  await expect(selectedCount(page)).toHaveText('2')

  // narrow to the C's — neither ticked row is on screen any more
  await search(page, 'cr')
  await expect(page.locator('[data-atom-table-row]')).toHaveCount(1)
  await expect(page.locator('[data-name="Apple"]')).toHaveCount(0)

  // KEY ASSERTION: the count held across the filter change. The default table
  // would read 0 here.
  await expect(selectedCount(page)).toHaveText('2')

  // tick one of the new rows — it adds to the batch rather than starting one
  await rowCheckbox(page, 'Cranberry').click()
  await expect(selectedCount(page)).toHaveText('3')

  // and the bulk action is actually handed all three
  await page.locator('[data-report]').click()
  await expect(page.locator('[data-reported]')).toHaveText('3')
})

test('a row that survives the filter comes back still ticked', async ({ page }) => {
  await page.goto('/atom/e2e/sticky-selection')
  await page.waitForLoadState('networkidle')

  await rowCheckbox(page, 'Apple').click()
  await rowCheckbox(page, 'Banana').click()

  // "an" matches Banana (ticked) and Cranberry (not) — the rows are re-rendered
  // by the Livewire round-trip, so the tick has to be re-derived from the id list
  // rather than being whatever the DOM happened to be left holding
  await search(page, 'an')
  await expect(page.locator('[data-atom-table-row]')).toHaveCount(2)

  await expect(rowCheckbox(page, 'Banana')).toHaveAttribute('data-checked', /.+/)
  await expect(rowCheckbox(page, 'Cranberry')).not.toHaveAttribute('data-checked', /.+/)

  // and unticking the re-shown row takes it back out of the batch
  await rowCheckbox(page, 'Banana').click()
  await expect(selectedCount(page)).toHaveText('1')

  await page.locator('[data-report]').click()
  await expect(page.locator('[data-reported]')).toHaveText('1')
})

test('show selected lists the whole batch, filter and all', async ({ page }) => {
  await page.goto('/atom/e2e/sticky-selection')
  await page.waitForLoadState('networkidle')

  await rowCheckbox(page, 'Apple').click()
  await rowCheckbox(page, 'Banana').click()

  // "an" drops Apple out of the list, so the batch is no longer reviewable
  await search(page, 'an')
  await expect(page.locator('[data-name="Apple"]')).toHaveCount(0)

  // KEY ASSERTION: the toggle lists the selection, ignoring the search that hid
  // half of it — Apple is back alongside Banana, and Cranberry is gone
  await page.locator('[data-atom-table-show-selected]').click()
  await expect(page.locator('[data-atom-table-row]')).toHaveCount(2)
  await expect(page.locator('[data-name="Apple"]')).toHaveCount(1)
  await expect(page.locator('[data-name="Banana"]')).toHaveCount(1)
  await expect(page.locator('[data-name="Cranberry"]')).toHaveCount(0)

  // every listed row is ticked, and unticking one drops it from the batch
  await expect(page.locator('[data-atom-table-row] [data-checked]')).toHaveCount(2)
  await rowCheckbox(page, 'Apple').click()
  await expect(selectedCount(page)).toHaveText('1')

  // flipping back returns to the search results
  await page.locator('[data-atom-table-show-selected]').click()
  await expect(page.locator('[data-name="Cranberry"]')).toHaveCount(1)
})

test('the persistent clear empties a selection that is off-screen', async ({ page }) => {
  await page.goto('/atom/e2e/sticky-selection')
  await page.waitForLoadState('networkidle')

  await rowCheckbox(page, 'Apple').click()
  await rowCheckbox(page, 'Banana').click()

  // filter the ticked rows out of view — untickng them is no longer possible,
  // which is exactly why the bar carries its own way out
  await search(page, 'cherry')
  await expect(selectedCount(page)).toHaveText('2')

  await page.locator('[data-atom-table-clear-selection]').click()
  await expect(page.locator('[data-atom-table-checked]')).toHaveCount(0)
})
