// NOTE: Loading-state (pagination overlay) is covered by the Tier-B render test
// tests/Feature/TableLoadingTest.php which asserts the overlay markup + wire:target;
// a full round-trip E2E was skipped because serving a Livewire fixture under
// `testbench serve` requires workbench plumbing (workbench/routes/web.php +
// workbench Livewire component + testbench.yaml discovers.web) out of proportion
// to the value. The render test already asserts the structural contract (overlay
// div, wire:target="$parent", x-show binding); the missing piece is a live
// Livewire round-trip which would need a proper workbench app setup.

import { test, expect } from '@playwright/test'

test('overflow=card toggles an expandable filter panel', async ({ page }) => {
  // Use the minimal fixture page which has the overflow=card bar second
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')

  // The second filters bar uses overflow="card"; it has a "More filters" button
  const more = page.getByRole('button', { name: /More filters/i }).first()
  await more.click()

  // After clicking, the overflow panel expands and the Category filter becomes visible
  // (the select trigger is role=combobox since the v3.5.19 ARIA rewrite; label is child text)
  await expect(page.getByRole('combobox').filter({ hasText: 'Category' })).toBeVisible()
})
