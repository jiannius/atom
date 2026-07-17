import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/context-menu (plain Alpine). Asserts
// open/close state + aria only, NOT pixel position — /atom/docs ships no
// Tailwind, so cursor geometry can't be verified here (see atom-testing memory).

// The trigger wrapper is display:contents (no box), so right-click the inner
// target element — the contextmenu event bubbles up to the wrapper's listener.
const targetOf = (root) => root.locator('[data-atom-context-menu-trigger] > *').first()

test('right-click opens the menu and an item click closes it', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').first()
  const menu = root.locator('[data-atom-menu]')

  await expect(menu).toBeHidden()

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()
  await expect(root).toHaveAttribute('data-open', '')

  await menu.getByRole('menuitem', { name: 'Edit' }).click()
  await expect(menu).toBeHidden()
})

test('escape closes the menu and clears data-open', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').first()
  const menu = root.locator('[data-atom-menu]')

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(root).not.toHaveAttribute('data-open', '')
})

test('a locked context menu stays open when an item is clicked', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').nth(1) // locked demo
  const menu = root.locator('[data-atom-menu]')

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()

  await menu.getByRole('menuitem').first().click()
  await expect(menu).toBeVisible()
})

test('the native browser context menu is suppressed', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const trigger = page.locator('[data-atom-context-menu-trigger]').first()

  // the component registers its contextmenu listener at init (before this one),
  // so it runs first and preventDefault()s — our listener then sees it prevented
  const prevented = await trigger.evaluate(
    (el) =>
      new Promise((resolve) => {
        el.addEventListener('contextmenu', (e) => resolve(e.defaultPrevented), { once: true })
        el.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }))
      })
  )

  expect(prevented).toBe(true)
})
