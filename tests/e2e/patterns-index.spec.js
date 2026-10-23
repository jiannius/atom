import { test, expect } from '@playwright/test'

// The patterns overview (/atom/docs/patterns): every pattern page with when to use it, reachable from the docs nav and the
// docs index, and each link opens its page.

const PATTERNS = ['app-shell-sidebar', 'app-shell-header', 'record-page', 'list-page', 'document-page', 'form-page', 'slide-over-form']

test('lists every pattern page with a link, when to use it and the file to read', async ({ page }) => {
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))

  await page.goto('/atom/docs/patterns')
  await expect(page.getByRole('heading', { name: 'Patterns' }).first()).toBeVisible()

  for (const name of PATTERNS) {
    const card = page.getByTestId(`pattern-${name}`)

    await expect(card).toBeVisible()
    await expect(card.locator(`a[href$="/atom/docs/patterns/${name}"]`)).toHaveCount(1)
    await expect(card).toContainText(`docs/patterns/${name}.blade.php`)
  }

  for (const shared of ['nav', 'crumbs', 'page']) {
    await expect(page.locator('main')).toContainText(`docs/patterns/${shared}.blade.php`)
  }

  expect(errors).toEqual([])
})

test('opens a pattern page from its card, and the nav and the docs index lead back to the overview', async ({ page }) => {
  await page.goto('/atom/docs/patterns')
  await page.getByTestId('pattern-list-page').locator('a').first().click()
  await expect(page).toHaveURL(/\/atom\/docs\/patterns\/list-page$/)
  await expect(page.getByTestId('list-panel')).toBeVisible()

  await page.goto('/atom/docs/patterns')
  await expect(page.locator('nav a[href$="/atom/docs/patterns"]')).toHaveCount(1)

  await page.goto('/atom/docs')
  await page.locator('main a[href$="/atom/docs/patterns"]').click()
  await expect(page).toHaveURL(/\/atom\/docs\/patterns$/)
})
