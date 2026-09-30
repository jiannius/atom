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

  // the trigger carries its name as aria-label since the filter-name fix, so it can be
  // matched the way assistive tech resolves it
  await bar.getByRole('combobox', { name: 'Status' }).first().click()

  // click the Published option (rendered by Alpine x-for into the dropdown on open)
  await page.locator('[data-atom-option]').filter({ hasText: 'Published' }).first().click()

  // KEY ASSERTION: a chip appears in the bar showing "Published".
  // This proves: wire:model → $filterKey set → $watch('selectValue') fires →
  // $dispatch('table-filter:set') dispatched → bar's x-on:table-filter:set.window
  // handler called → chip auto-register worked end-to-end.
  const chip = bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Published' }).first()
  await expect(chip).toBeVisible()
  await expect(chip).toContainText('Published')

  // clear via the chip ✕ button
  await chip.getByRole('button').click()

  // chip is gone
  await expect(bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Published' })).toHaveCount(0)
})

test('filter options render eagerly, before any dropdown is opened', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  // Options are populated by the select() Alpine factory at init() and rendered
  // by x-for into the (still-closed) popover DOM — NOT lazily on first open.
  // Before the eager-population fix, this.options stayed [] until onOpen(), so a
  // concurrent Livewire morph left an un-opened filter empty ("No Results").
  // Assert the option DOM already exists while every dropdown is still closed.
  await expect(page.locator('[data-atom-option]').filter({ hasText: 'Published' })).toHaveCount(1)
  await expect(page.locator('[data-atom-option]').filter({ hasText: 'Type A' })).toHaveCount(1)
  await expect(page.locator('[data-atom-option]').filter({ hasText: 'Category X' })).toHaveCount(1)
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
  await expect(bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Published' })).toHaveCount(0)
  await expect(clearAll).toHaveCount(0)
})

// A filter select takes no `label` prop from <atom:table.filters>, so there is no field
// label to anchor to, and role="combobox" takes no accessible name from its own content
// the way a button does. The trigger showed "Status" and announced nothing — reported on
// three smgdms listings (jiannius/atom#38). Asserting through getByRole checks that the
// name actually COMPUTES, which is what a screen reader does; the Pest suite can only see
// that the attribute is present.
test('every filter trigger resolves an accessible name', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').first()

  for (const name of ['Status', 'Type']) {
    await expect(bar.getByRole('combobox', { name })).toHaveCount(1)
  }

  // and nothing in the bar is left unnamed
  const unnamed = await bar.getByRole('combobox').evaluateAll(
    els => els.filter(el => !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby')).length
  )

  expect(unnamed, 'a filter combobox has no accessible name').toBe(0)
})

// overflow="modal" moves the slot into a <dialog>, which the browser paints in the top
// layer — outside the filter bar's own stacking context, though not outside its subtree.
// The chip wiring is window-level, so it should reach the bar behind the dialog; that is
// the thing worth proving, because a control that quietly stopped registering would still
// filter and look fine.
test('the overflow modal opens, and its filters still register chips in the bar', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').nth(2)
  const dialog = bar.locator('dialog')

  await expect(dialog).toBeHidden()

  await bar.getByRole('button', { name: /More filters/ }).click()
  await expect(dialog).toBeVisible()

  await dialog.getByRole('combobox', { name: 'Brand' }).click()
  await page.locator('[data-atom-option]').filter({ hasText: 'Brand One' }).first().click()

  const chip = bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Brand One' }).first()
  await expect(chip).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()

  // the chip outlives the modal it was set in — it is the bar's record of the filter
  await expect(chip).toBeVisible()
})

// The bar variants register themselves off a wire:model; a form control has to be
// told to, with `table-filter`. This is the pattern a host writes in the modal:
// a normal labelled field that still lands in the bar's chip row.
test('a table-filter listbox in the modal chips, and the chip clears it', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').nth(2)
  const dialog = bar.locator('dialog')

  await bar.getByRole('button', { name: /More filters/ }).click()
  await expect(dialog).toBeVisible()

  await dialog.getByRole('combobox', { name: 'Colour' }).click()
  await page.locator('[data-atom-option]').filter({ hasText: 'Blue' }).first().click()

  // the chip carries the FIELD's label, not the option's
  const chip = bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Blue' }).first()
  await expect(chip).toBeVisible()
  await expect(chip).toContainText('Colour')

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await expect(chip).toBeVisible()

  await chip.getByRole('button').click()
  await expect(bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Blue' })).toHaveCount(0)

  // and the control behind it is empty again, not just the chip gone
  await bar.getByRole('button', { name: /More filters/ }).click()
  await expect(dialog.getByRole('combobox', { name: 'Colour' })).not.toContainText('Blue')
})

test('a table-filter native select chips its option label, not its value', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  const bar = page.locator('[data-atom-table-filters]').nth(2)
  const dialog = bar.locator('dialog')

  await bar.getByRole('button', { name: /More filters/ }).click()
  await dialog.locator('select').selectOption('l')

  // the model holds 'l'; the chip has to read "Large" back out of the option
  const chip = bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Size' }).first()
  await expect(chip).toContainText('Large')

  // the chip lives in the bar, so it is only reachable once the modal is closed —
  // an open dialog holds the top layer and swallows the click
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()

  await chip.getByRole('button').click()
  await expect(bar.locator('[data-atom-table-filter-chip]').filter({ hasText: 'Size' })).toHaveCount(0)

  await bar.getByRole('button', { name: /More filters/ }).click()
  await expect(dialog.locator('select')).toHaveValue('')
})
