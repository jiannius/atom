import { test, expect } from '@playwright/test'

test('atom docs button page boots Alpine and renders the ghost-color demo', async ({ page }) => {
  await page.goto('/atom/docs/button')
  await expect(page.getByRole('button', { name: 'Primary' }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Danger' }).first()).toBeVisible()
})
