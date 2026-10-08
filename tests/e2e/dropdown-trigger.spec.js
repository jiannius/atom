import { test, expect } from '@playwright/test'

// Drives /atom/e2e/dropdown-trigger (resources/views/e2e/dropdown-trigger.blade.php), issue #77.
//
// dropdown.js found its trigger with a fallback `querySelector('button')` over the WHOLE root,
// popover included. With a trigger that is not a <button> (an <atom:link>) the first menu item,
// which is a <button>, became "the trigger", so a click on an option counted as a trigger click
// and re-opened the menu the same click had just closed.
//
// Real mouse clicks throughout: the bug lives in the click handler on the root.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)

test('a non-button trigger: clicking an item closes the menu and it stays closed', async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-trigger')

  const root = probe(page, 'link').locator('[data-atom-dropdown]')
  const trigger = probe(page, 'link').getByText('Select')
  const menu = root.locator('[data-atom-menu]')

  await expect(menu).toBeHidden()
  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')

  await menu.getByRole('menuitem', { name: 'Alpha' }).click()
  await expect(menu).toBeHidden()
  await expect(root).not.toHaveAttribute('data-open')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')

  // It must not re-open a beat later either.
  await page.waitForTimeout(300)
  await expect(menu).toBeHidden()

  // And the link still toggles it afterwards, with a second item.
  await trigger.click()
  await expect(menu).toBeVisible()
  await menu.getByRole('menuitem', { name: 'Beta' }).click()
  await expect(menu).toBeHidden()
})

test('a non-button trigger: the first item closes a menu that is already open', async ({ page }) => {
  // The literal report: with the menu open by any route, a click on the FIRST item counted as
  // a trigger click (show() is a no-op on an open popover) and the close branch never ran.
  await page.goto('/atom/e2e/dropdown-trigger')

  const menu = probe(page, 'link').locator('[data-atom-menu]')

  await menu.evaluate(el => el.showPopover())
  await expect(menu).toBeVisible()

  await menu.getByRole('menuitem', { name: 'Alpha' }).click()
  await expect(menu).toBeHidden()
})

test('a non-button trigger keeps aria on the link, not on a menu item', async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-trigger')

  const menu = probe(page, 'link').locator('[data-atom-menu]')

  await expect(probe(page, 'link').getByText('Select')).toHaveAttribute('aria-haspopup', 'menu')
  // The menu is closed, so look the item up by attribute: a role query skips hidden elements.
  const firstItem = menu.locator('[data-atom-menu-item]').first()

  await expect(firstItem).not.toHaveAttribute('aria-haspopup')
  await expect(firstItem).not.toHaveAttribute('aria-expanded')
})

test('a button trigger still opens, and an item click still closes it', async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-trigger')

  const trigger = probe(page, 'button').getByRole('button', { name: 'Pick' })
  const menu = probe(page, 'button').locator('[data-atom-menu]')

  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')

  await menu.getByRole('menuitem', { name: 'Beta' }).click()
  await expect(menu).toBeHidden()
  await page.waitForTimeout(300)
  await expect(menu).toBeHidden()
})

test('data-atom-dropdown-trigger still wins over an earlier button', async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-trigger')

  const decoy = probe(page, 'explicit').getByRole('button', { name: 'Decoy' })
  const trigger = probe(page, 'explicit').getByText('Explicit')
  const menu = probe(page, 'explicit').locator('[data-atom-menu]')

  // The decoy is a button that comes first, but it is not the trigger.
  await decoy.click()
  await expect(menu).toBeHidden()

  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(decoy).not.toHaveAttribute('aria-expanded')

  await menu.getByRole('menuitem', { name: 'Alpha' }).click()
  await expect(menu).toBeHidden()
})

test('the docs demo with a link trigger closes on an item click', async ({ page }) => {
  await page.goto('/atom/docs/dropdown')

  const root = page.locator('[data-atom-dropdown]').filter({ has: page.getByText('Choose a prefix', { exact: true }) })
  const menu = root.locator('[data-atom-menu]')

  await root.getByText('Choose a prefix', { exact: true }).click()
  await expect(menu).toBeVisible()

  await menu.getByRole('menuitem', { name: 'INV' }).click()
  await expect(menu).toBeHidden()
  await page.waitForTimeout(300)
  await expect(menu).toBeHidden()
})
