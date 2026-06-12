import { test, expect } from '@playwright/test'

// Drives the live confirm/alert/toast demos on /atom/docs. The confirm and
// alert dialogs are identified by the modal name baked into their x-data; the
// toast is the manual popover tagged data-atom-toast.
const dialogFor = (page, name) => page.locator(`dialog[x-data*="${name}"]`)
const toast = (page) => page.locator('[data-atom-toast]')

test.describe('confirm', () => {
  test('opens on trigger, resolves on confirm, and fires the success toast', async ({ page }) => {
    await page.goto('/atom/docs/confirm')

    const dialog = dialogFor(page, 'atom-confirm')
    await expect(dialog).toBeHidden()

    await page.getByRole('button', { name: 'Delete', exact: true }).click()
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText('Delete customer?')

    await dialog.getByRole('button', { name: 'Confirm' }).click()
    await expect(dialog).toBeHidden()
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Confirmed.')
  })

  test('rejects (no toast) when cancelled', async ({ page }) => {
    await page.goto('/atom/docs/confirm')

    const dialog = dialogFor(page, 'atom-confirm')
    await page.getByRole('button', { name: 'Delete', exact: true }).click()
    await expect(dialog).toBeVisible()

    await dialog.getByRole('button', { name: 'Cancel' }).click()
    await expect(dialog).toBeHidden()
    await expect(toast(page)).toBeHidden()
  })
})

test.describe('alert', () => {
  test('opens on trigger and dismisses via the button', async ({ page }) => {
    await page.goto('/atom/docs/alert')

    const dialog = dialogFor(page, 'atom-alert')
    await expect(dialog).toBeHidden()

    await page.getByRole('button', { name: 'Show alert' }).click()
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText('Heads up')

    await dialog.getByRole('button', { name: 'Got it' }).click()
    await expect(dialog).toBeHidden()
  })
})

test.describe('toast', () => {
  test('shows on trigger and closes via the X button', async ({ page }) => {
    await page.goto('/atom/docs/toast')

    await expect(toast(page)).toBeHidden()

    await page.getByRole('button', { name: 'Success' }).click()
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Saved successfully.')

    await toast(page).getByRole('button', { name: 'Close' }).click()
    await expect(toast(page)).toBeHidden()
  })

  test('auto-dismisses after the default delay', async ({ page }) => {
    await page.goto('/atom/docs/toast')

    await page.getByRole('button', { name: 'Center' }).click()
    await expect(toast(page)).toBeVisible()

    // default delay is 3000ms — give it headroom
    await expect(toast(page)).toBeHidden({ timeout: 5000 })
  })
})
