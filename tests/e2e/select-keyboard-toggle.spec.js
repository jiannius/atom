import { test, expect } from '@playwright/test'

// Drives /atom/e2e/dropdown-trigger (the `listbox` and `button` probes), a regression from the #79 toggle.
//
// A non-searchable listbox handles Space and printable keys itself (select.js typeAhead): the keydown opens
// it. The browser then fires its own click for a Space on a button on keyup, with detail 0; the dropdown
// click handler reads a keyboard click from the live state, found the menu open and closed it. So Space
// could not open a closed listbox, and typing "New York" closed it at the space.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const trigger = (page) => probe(page, 'listbox').locator('[data-atom-dropdown] > button')
const menu = (page) => probe(page, 'listbox').locator('[data-atom-menu][popover]')
const isOpen = (loc) => loc.evaluate((el) => el.matches(':popover-open'))

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-trigger')
  await page.waitForFunction(() => window.Livewire && window.Alpine)
})

test('Space on a closed non-searchable listbox opens it and leaves it open', async ({ page }) => {
  await trigger(page).focus()
  await page.keyboard.press('Space')

  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)
  await expect(trigger(page)).toHaveAttribute('aria-expanded', 'true')
})

test('typing "new y" keeps the listbox open', async ({ page }) => {
  await trigger(page).focus()
  await page.keyboard.type('new y')

  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)
})

test('t, Space, h keeps it open', async ({ page }) => {
  await trigger(page).focus()
  await page.keyboard.type('t')
  await page.keyboard.press('Space')

  // the space itself must not have closed it (the next letter would quietly open it again)
  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)

  await page.keyboard.type('h')
  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)
})

test('Space typed while it is already open does not close it', async ({ page }) => {
  await trigger(page).click()
  expect(await isOpen(menu(page))).toBe(true)

  await page.keyboard.type('new ')
  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)
})

test('Enter opens a closed listbox, then picks the active option and closes it', async ({ page }) => {
  await trigger(page).focus()
  await page.keyboard.press('Enter')
  await page.waitForTimeout(200)
  expect(await isOpen(menu(page))).toBe(true)

  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Enter')
  await expect.poll(() => isOpen(menu(page))).toBe(false)
  await expect(trigger(page)).toContainText(/New York|Newark/)
})

test('a plain dropdown still closes on a second click and on Enter (#79)', async ({ page }) => {
  const button = probe(page, 'button').getByRole('button', { name: 'Pick' })
  const plain = probe(page, 'button').locator('[data-atom-menu][popover]')

  await button.click()
  expect(await isOpen(plain)).toBe(true)
  await button.click()
  expect(await isOpen(plain)).toBe(false)

  await button.focus()
  await page.keyboard.press('Enter')
  expect(await isOpen(plain)).toBe(true)
  await page.keyboard.press('Enter')
  expect(await isOpen(plain)).toBe(false)
})
