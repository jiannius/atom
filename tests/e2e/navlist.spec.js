import { test, expect } from '@playwright/test'

// Drives the live navlist demo on /atom/docs/navlist.
//
// NOTE: pixel-layout assertions are unreliable here — the package-served
// /atom/docs only ships atom's ~2KB base CSS, not a full Tailwind build, so
// layout utilities (flex/items-center/...) are absent. The icon↔label
// alignment fix is verified by NavlistTest (source) + humblebear (full
// Tailwind). x-show visibility below is driven by Alpine, not Tailwind, so it
// is valid in this environment.

test('expandable navlist group toggles via the Alpine disclosure', async ({ page }) => {
  await page.goto('/atom/docs/navlist')

  const group = page
    .locator('[data-atom-navlist-group]')
    .filter({ has: page.getByRole('button', { name: 'Reports' }) })
  const sales = group.getByRole('link', { name: 'Sales' })

  // defaults expanded
  await expect(sales).toBeVisible()

  await group.getByRole('button', { name: 'Reports' }).click()
  await expect(sales).toBeHidden()

  await group.getByRole('button', { name: 'Reports' }).click()
  await expect(sales).toBeVisible()
})
