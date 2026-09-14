// NOTE: the table's loading overlay is covered end-to-end by
// tests/e2e/table-loading.spec.js, against the Livewire fixture at
// /atom/e2e/table-loading.
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
