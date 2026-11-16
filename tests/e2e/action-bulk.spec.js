import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:action> in the table's bulk bar (tests/Fixtures/ActionTableFixture.php, resources/views/e2e/action-table.blade.php): three buttons, the delete pinned last
// outside the three and asking with the count, the ⋯ after it with the warning and the `checked-menu` rows, in a `tone="accent"` bar too.
// Measured on the consumer's Tailwind (`node tests/consumer/build.mjs`).

const bar = (page) => page.locator('[data-atom-table-checked]')
const group = (page) => bar(page).locator(':scope > div:last-child')
const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const more = (page) => group(page).getByRole('button', { name: 'More actions' })
const menu = (page) => group(page).locator('[data-atom-menu][popover]')

const show = async (page, { tone = null } = {}) => {
  await open(page, { path: 'action-table', extra: tone ? `&tone=${tone}` : '' })
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const tick = async (page, count = 3) => {
  for (let index = 0; index < count; index++) {
    await page.getByRole('checkbox', { name: 'Select row' }).nth(index).click()
  }

  await expect(bar(page)).toBeVisible()
  await expect(bar(page)).toContainText(`${count} Selected`)
}

const placement = (page) => group(page).evaluate((el) => [...el.children].map((child) => child.hasAttribute('data-atom-action')
  ? `${child.innerText.replace(/\s+/g, ' ').trim()}:${child.getAttribute('data-atom-action')}`
  : child.hasAttribute('data-atom-dropdown') ? 'menu' : child.nodeName.toLowerCase()))

const rows = (page) => menu(page).evaluate((el) => [...el.children].map((child) => child.getAttribute('role') === 'separator' ? '|' : child.textContent.replace(/\s+/g, ' ').trim()))

test('tick 3: three buttons, the delete pinned last, the ⋯ after it', async ({ page }) => {
  await show(page)
  await expect(bar(page)).toBeHidden()
  await tick(page)

  expect(await placement(page)).toEqual(['Assign:visible', 'Tag:visible', 'Move:visible', 'Delete:pinned', 'menu'])
  await expect(more(page)).toBeVisible()

  await more(page).click()
  await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  expect(await rows(page)).toEqual(['Merge', 'Copy ids', '|', 'Void'])
})

test('the pinned delete asks "Delete 3 records?" and, accepted, calls delete() for the selection', async ({ page }) => {
  await show(page)
  await tick(page)

  await page.getByTestId('delete').click()
  await expect(dialog(page)).toHaveAttribute('data-open', '')
  await expect(dialog(page).locator('.text-lg')).toHaveText('Delete 3 records?')
  await dialog(page).getByRole('button', { name: 'Cancel' }).click()
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  expect(await log(page)).toEqual([])

  await page.getByTestId('delete').click()
  await dialog(page).locator('button[type="submit"]').click()
  await expect.poll(() => log(page)).toEqual(['delete:3'])
  await expect(bar(page)).toBeHidden()
})

test('a warning row in the ⋯ asks in warning and calls once', async ({ page }) => {
  await show(page)
  await tick(page, 2)
  await more(page).click()
  await page.getByTestId('void').click()

  await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
  await dialog(page).locator('button[type="submit"]').click()
  await expect.poll(() => log(page)).toEqual(['void'])
})

test('a visible bulk action calls once and asks nothing', async ({ page }) => {
  await show(page)
  await tick(page, 1)
  await page.getByTestId('assign').click()

  await expect.poll(() => log(page)).toEqual(['assign'])
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
})

test('the ⋯ is last in the bar and the delete is the tint, in a tone="accent" bar too', async ({ page }) => {
  await show(page, { tone: 'accent' })
  await tick(page)

  await expect(bar(page)).toHaveAttribute('data-tone', 'accent')
  expect(await placement(page)).toEqual(['Assign:visible', 'Tag:visible', 'Move:visible', 'Delete:pinned', 'menu'])
  await expect(page.getByTestId('delete')).toHaveClass(/bg-danger-bg/)
  await expect(page.getByTestId('delete')).toBeVisible()
  await more(page).click()
  await expect(menu(page).getByRole('menuitem', { name: 'Copy ids' })).toBeVisible()
})

test('a morph that adds an action moves the last button into the open ⋯ and fires no call', async ({ page }) => {
  await show(page)
  await tick(page)
  await more(page).click()
  await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)

  await page.evaluate(() => window.Livewire.first().call('addReminder'))
  await expect.poll(() => placement(page)).toEqual(['Send reminder:visible', 'Assign:visible', 'Tag:visible', 'Delete:pinned', 'menu'])

  expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  expect(await rows(page)).toEqual(['Move', 'Merge', 'Copy ids', '|', 'Void'])
  expect(await log(page)).toEqual([])
})

test('the selection survives a re-render and the bar keeps its order', async ({ page }) => {
  await show(page)
  await tick(page)

  await page.evaluate(() => window.Livewire.first().call('bump'))
  await expect(page.getByTestId('renders')).toHaveText('1')

  await expect(bar(page)).toContainText('3 Selected')
  expect(await placement(page)).toEqual(['Assign:visible', 'Tag:visible', 'Move:visible', 'Delete:pinned', 'menu'])
})
