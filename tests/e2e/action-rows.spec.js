import { test, expect } from '@playwright/test'

// <atom:action> in a table row's ⋯ menu (tests/Fixtures/ActionRowsFixture.php, resources/views/e2e/action-rows.blade.php): a row holds no visible action, so
// every action is a row of the ⋯, neutral first, then a separator, then warning, then delete. The ⋯ is in the row's actions cell, which stops the click, so the
// row's own `href` is not followed. The page is on the consumer's Tailwind (latest); placement is read from data attributes.

const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const cell = (page, id) => page.getByTestId(`cell-${id}`)
const trigger = (page, id) => cell(page, id).locator('[data-atom-dropdown] > button')
const menu = (page, id) => cell(page, id).locator('[data-atom-menu]')
const settled = (page) => page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 400)))

const open = async (page) => {
  await page.goto('/atom/e2e/action-rows')
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const openMenu = async (page, id = 1) => {
  await trigger(page, id).click()
  await expect.poll(() => menu(page, id).evaluate((el) => el.matches(':popover-open'))).toBe(true)
}

const rows = (page, id = 1) => menu(page, id).evaluate((el) => [...el.children].map((child) => child.getAttribute('role') === 'separator' ? '|' : child.textContent.replace(/\s+/g, ' ').trim()))

test.describe('placement', () => {
  test('every action is a row of the ⋯: neutral, the low one, a separator, warning, then delete; none is a button in the row', async ({ page }) => {
    await open(page)

    await expect(cell(page, 1).locator('[data-atom-action]')).toHaveCount(5)
    await expect(cell(page, 1).locator('[data-atom-action]:not([data-atom-action="menu"])')).toHaveCount(0)
    await expect(cell(page, 1).locator('> *')).toHaveCount(1)
    await openMenu(page)
    expect(await rows(page)).toEqual(['Edit', 'Open in full', 'Copy link', '|', 'Void', 'Delete'])
  })

  test('every action is drawn once, and no marker reaches the browser', async ({ page }) => {
    await open(page)

    for (const name of ['edit', 'open', 'copy', 'void', 'delete']) {
      await expect(page.getByTestId(`${name}-1`)).toHaveCount(1)
    }

    expect(await page.content()).not.toContain('atom:action:')
  })
})

test.describe('the row\'s own href', () => {
  test('a click on the ⋯ opens the menu and does not follow the row\'s href', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await settled(page)

    expect(new URL(page.url()).pathname).toBe('/atom/e2e/action-rows')
  })

  test('Enter and Space on the ⋯ open the menu without following the href', async ({ page }) => {
    await open(page)

    for (const key of ['Enter', 'Space']) {
      await trigger(page, 1).focus()
      await page.keyboard.press(key)
      await expect.poll(() => menu(page, 1).evaluate((el) => el.matches(':popover-open'))).toBe(true)
      expect(new URL(page.url()).pathname).toBe('/atom/e2e/action-rows')
      await page.keyboard.press('Escape')
      await expect.poll(() => menu(page, 1).evaluate((el) => el.matches(':popover-open'))).toBe(false)
    }
  })

  test('a click on a menu action runs its own call and does not follow the row\'s href', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('edit-1').click()
    await settled(page)

    expect(await log(page)).toEqual(['edit-1'])
    expect(new URL(page.url()).pathname).toBe('/atom/e2e/action-rows')
  })

  test('an action with an href goes to its own href, not the row\'s', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('open-1').click()

    await expect(page.getByTestId('landing')).toBeVisible()
    expect(new URL(page.url()).search).toBe('?from=open-1')
  })

  test('the rest of the row still opens its href', async ({ page }) => {
    await open(page)
    await page.getByText('Record 1', { exact: true }).click()

    await expect(page.getByTestId('landing')).toBeVisible()
    expect(new URL(page.url()).search).toBe('?row=1')
  })
})

test.describe('a toned action asks first', () => {
  test('a delete row asks in danger with the delete copy; Cancel makes no call and gives the focus back to the ⋯', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('delete-1').click()

    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(dialog(page)).toContainText('Delete this record?')
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await settled(page)

    expect(await log(page)).toEqual([])
    expect(new URL(page.url()).pathname).toBe('/atom/e2e/action-rows')
    await expect(trigger(page, 1)).toBeFocused()
  })

  test('Accept makes exactly one call, with the action\'s own wire:click', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('delete-1').click()
    await dialog(page).locator('button[type="submit"]').click()
    await settled(page)

    expect(await log(page)).toEqual(['destroy-1'])
  })

  test('a delete with no action of its own calls $wire.delete() once the dialog accepts', async ({ page }) => {
    await open(page)
    await openMenu(page, 2)
    await page.getByTestId('delete-2').click()
    await dialog(page).locator('button[type="submit"]').click()
    await settled(page)

    expect(await log(page)).toEqual(['delete'])
  })

  test('a warning row asks in warning', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('void-1').click()

    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
    await dialog(page).locator('button[type="submit"]').click()
    await settled(page)

    expect(await log(page)).toEqual(['void-1'])
  })

  test('an untoned row asks nothing', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('copy-1').click()
    await settled(page)

    expect(await log(page)).toEqual(['copy-1'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })
})

test('an open row menu survives a re-render of the component', async ({ page }) => {
  await open(page)
  await openMenu(page)
  await page.getByTestId('bump').dispatchEvent('click')
  await expect(page.getByTestId('renders')).toHaveText('1')

  expect(await menu(page, 1).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  expect(await rows(page)).toEqual(['Edit', 'Open in full', 'Copy link', '|', 'Void', 'Delete'])
})
