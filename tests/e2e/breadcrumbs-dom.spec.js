import { test, expect } from '@playwright/test'

// breadcrumbs.spec.js drives the trail-merging logic with a stubbed document, so
// it cannot see how the Livewire root is found in a real page. These cases render
// the actual sidebar layout — whose sr-only <h1> sits in front of the Livewire
// root when the page has a title — and assert the trail still resolves.

test('renders the trail inside a titled sidebar layout', async ({ page }) => {
  await page.goto('/atom/e2e/breadcrumbs')

  await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Dashboard')
})

test('the page title h1 precedes the Livewire root it labels', async ({ page }) => {
  await page.goto('/atom/e2e/breadcrumbs')

  const firstChildTag = await page.locator('[data-atom-main] > *').first().evaluate(el => el.tagName)
  const rootIsDeeper = await page.locator('[data-atom-main] > [wire\\:id]').count()

  expect(firstChildTag).toBe('H1')
  expect(rootIsDeeper).toBe(1)
})
