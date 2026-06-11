import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/dropdown. The basic demo has an
// "Options" trigger and a menu of items.
const rootFor = (page, triggerName) =>
  page.locator('[data-atom-dropdown]').filter({ has: page.getByRole('button', { name: triggerName }) })

test('opens on the trigger and closes when an item is clicked', async ({ page }) => {
  await page.goto('/atom/docs/dropdown')

  const trigger = page.getByRole('button', { name: 'Options' })
  const root = rootFor(page, 'Options')
  const menu = root.locator('[data-atom-menu]')

  await expect(menu).toBeHidden()
  await expect(trigger).toHaveAttribute('aria-haspopup', 'menu')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')

  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(root).toHaveAttribute('data-open', '')
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')

  await menu.getByRole('menuitem', { name: 'Edit' }).click()
  await expect(menu).toBeHidden()
})

test('a native dismiss (escape) clears data-open and aria-expanded', async ({ page }) => {
  // Regression: data-open / aria-expanded were only reset in hide(), so a
  // native popover dismiss left them stale. They are now reset on the
  // toggle->closed event.
  await page.goto('/atom/docs/dropdown')

  const trigger = page.getByRole('button', { name: 'Options' })
  const root = rootFor(page, 'Options')
  const menu = root.locator('[data-atom-menu]')

  await trigger.click()
  await expect(menu).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(root).not.toHaveAttribute('data-open')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
})
