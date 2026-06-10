import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/modal. The <dialog> is identified by the
// modal name baked into its x-data config.
const dialogFor = (page, name) => page.locator(`dialog[x-data*="${name}"]`)

test('opens and closes the basic modal', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-basic')
  await expect(dialog).toBeHidden()

  await page.getByRole('button', { name: 'Open modal' }).click()
  await expect(dialog).toBeVisible()
  await expect(dialog).toHaveAttribute('data-open', '')
  await expect(dialog).toHaveAttribute('data-atom-modal', /.*/)

  await dialog.getByRole('button', { name: 'Close', exact: true }).filter({ hasText: 'Close' }).click()
  await expect(dialog).toBeHidden()
  await expect(dialog).not.toHaveAttribute('data-open')
  await expect(dialog).not.toHaveAttribute('data-atom-modal')
})

test('closes via the X button', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-basic')
  await page.getByRole('button', { name: 'Open modal' }).click()
  await expect(dialog).toBeVisible()

  await dialog.locator('button[aria-label="Close"]:not([data-atom-button])').click()
  await expect(dialog).toBeHidden()
})

test('closes on escape', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-basic')
  await page.getByRole('button', { name: 'Open modal' }).click()
  await expect(dialog).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
})

test('closes on backdrop click', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-basic')
  await page.getByRole('button', { name: 'Open modal' }).click()
  await expect(dialog).toBeVisible()

  await page.mouse.click(5, 5)
  await expect(dialog).toBeHidden()
})

test('slide variant sets the slide attribute and cleans up on close', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-slide')
  await page.getByRole('button', { name: 'Open slide-over' }).click()
  await expect(dialog).toBeVisible()
  await expect(dialog).toHaveAttribute('data-atom-modal-slide', /.*/)

  await dialog.locator('button[aria-label="Close"]:not([data-atom-button])').click()
  await expect(dialog).toBeHidden()
  await expect(dialog).not.toHaveAttribute('data-atom-modal-slide')
})

test('persistent modal ignores escape and backdrop clicks', async ({ page }) => {
  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-persistent')
  await page.getByRole('button', { name: 'Open persistent modal' }).click()
  await expect(dialog).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()

  await page.mouse.click(5, 5)
  await expect(dialog).toBeVisible()

  // closeable is independent of the two disabled switches — the X still works
  await dialog.locator('button[aria-label="Close"]:not([data-atom-button])').click()
  await expect(dialog).toBeHidden()
})

test('showing an already-open modal does not throw', async ({ page }) => {
  const errors = []
  page.on('pageerror', (err) => errors.push(err.message))

  await page.goto('/atom/docs/modal')

  const dialog = dialogFor(page, 'demo-basic')
  await page.evaluate(() => window.atom.modal('demo-basic').show())
  await page.evaluate(() => window.atom.modal('demo-basic').show())

  await expect(dialog).toBeVisible()
  expect(errors).toEqual([])
})
