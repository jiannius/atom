import { test, expect } from '@playwright/test'

// Issue #150: a paginated <atom:table> in a Livewire component that uses WithTable and no WithPagination
// (tests/Fixtures/TableOnlyFixture.php) changes page when the pager is clicked, instead of a MethodNotFoundException.

test('the pager of a component that only uses WithTable changes the page', async ({ page }) => {
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))

  await page.goto('/atom/e2e/table-only')
  await expect(page.getByText('Row 10', { exact: true })).toBeVisible()
  await expect(page.getByText('Row 11', { exact: true })).toHaveCount(0)

  const update = page.waitForResponse((r) => r.url().includes('/update') && r.request().method() === 'POST')
  await page.getByRole('button', { name: 'Go to page 2' }).click()
  const response = await update

  expect(response.status()).toBe(200)
  await expect(page.getByText('Row 11', { exact: true })).toBeVisible()
  await expect(page.getByText('Row 20', { exact: true })).toBeVisible()
  await expect(page.getByText('Row 10', { exact: true })).toHaveCount(0)
  expect(errors).toEqual([])
})
