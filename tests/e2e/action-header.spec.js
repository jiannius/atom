import { test, expect } from '@playwright/test'

// <atom:action> in a page header (tests/Fixtures/ActionsFixture.php, resources/views/e2e/actions.blade.php): the header collects its actions, draws
// the first two as buttons, the rest in the ⋯ menu (neutral, a separator, warning, danger) and the primary last. The page is on the consumer's Tailwind
// (latest). Placement is read from data attributes: the rig has no stylesheet of its own.

const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const group = (page) => page.locator('[data-atom-page-actions]')
const trigger = (page) => group(page).locator('[data-atom-dropdown] > button')
const menu = (page) => group(page).locator('[data-atom-menu]')
const settled = (page) => page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 400)))

const open = async (page) => {
  await page.goto('/atom/e2e/actions')
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const openMenu = async (page) => {
  await trigger(page).click()
  await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
}

// the group's children, by data-atom-action and name: `Edit:visible`, `menu`, `Send invoice:primary`
const placement = (page) => group(page).evaluate((el) => [...el.children].map((child) => child.hasAttribute('data-atom-action')
  ? `${(child.querySelector('[data-atom-button-label]') ?? child).textContent.replace(/\s+/g, ' ').trim()}:${child.getAttribute('data-atom-action')}`
  : child.hasAttribute('data-atom-dropdown') ? 'menu' : child.nodeName.toLowerCase()))

const rows = (page) => menu(page).evaluate((el) => [...el.children].map((child) => child.getAttribute('role') === 'separator' ? '|' : child.textContent.replace(/\s+/g, ' ').trim()))

test.describe('placement', () => {
  test('two buttons, then the ⋯, then the primary last; the rest in the menu after a separator', async ({ page }) => {
    await open(page)

    expect(await placement(page)).toEqual(['Edit:visible', 'Print:visible', 'menu', 'Send invoice:primary'])
    await openMenu(page)
    expect(await rows(page)).toEqual(['Duplicate', '|', 'Void invoice', 'Archive and leave', 'Retire invoice', 'Delete invoice'])
  })

  test('a warning action with priority="primary" is a menu row in its tone, never the header\'s primary', async ({ page }) => {
    await open(page)

    expect(await placement(page)).not.toContain('Retire invoice:primary')
    await expect(page.getByTestId('retire')).toHaveAttribute('data-atom-action', 'menu')
    await openMenu(page)
    await page.getByTestId('retire').click()
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
    await dialog(page).locator('button[type="submit"]').click()

    await expect.poll(() => log(page)).toEqual(['retire'])
  })

  test('every action is drawn once', async ({ page }) => {
    await open(page)

    for (const name of ['edit', 'print', 'duplicate', 'void', 'leave', 'retire', 'delete', 'send']) {
      await expect(page.getByTestId(name)).toHaveCount(1)
    }

    expect(await page.content()).not.toContain('atom:action:')
  })

  test('375px and 1440px draw the same actions; the group wraps under the title when it no longer fits', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await open(page)
    const wide = await placement(page)
    const beside = await page.evaluate(() => {
      const title = document.querySelector('[data-atom-page-header-title]').getBoundingClientRect()
      const actions = document.querySelector('[data-atom-page-actions]').getBoundingClientRect()

      return actions.top < title.bottom
    })

    await page.setViewportSize({ width: 375, height: 800 })
    await expect.poll(() => placement(page)).toEqual(wide)
    const below = await page.evaluate(() => {
      const title = document.querySelector('[data-atom-page-header-title]').getBoundingClientRect()
      const actions = document.querySelector('[data-atom-page-actions]').getBoundingClientRect()

      return actions.top >= title.bottom
    })

    expect(beside).toBe(true)
    expect(below).toBe(true)
  })
})

test.describe('the keyboard', () => {
  test('Tab visits the visible actions, then the ⋯, then the primary', async ({ page }) => {
    await open(page)
    await page.getByTestId('edit').focus()

    await page.keyboard.press('Tab')
    await expect(page.getByTestId('print')).toBeFocused()
    await page.keyboard.press('Tab')
    await expect(trigger(page)).toBeFocused()
    await page.keyboard.press('Tab')
    await expect(page.getByTestId('send')).toBeFocused()
  })

  test('Enter and Space open the ⋯, and its rows are reachable with Tab', async ({ page }) => {
    await open(page)

    for (const key of ['Enter', 'Space']) {
      await trigger(page).focus()
      await page.keyboard.press(key)
      await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)

      await page.keyboard.press('Tab')
      await expect(page.getByTestId('duplicate')).toBeFocused()
      await page.keyboard.press('Tab')
      await expect(page.getByTestId('void')).toBeFocused()

      await page.keyboard.press('Escape')
      await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
    }
  })
})

test.describe('a toned action asks first', () => {
  test('a warning row asks in warning; Cancel makes no call and gives the focus back to the ⋯', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('void').click()

    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(dialog(page)).toContainText('Void invoice?')
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await settled(page)

    expect(await log(page)).toEqual([])
    await expect(trigger(page)).toBeFocused()
  })

  test('Accept makes exactly one call', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('void').click()
    await dialog(page).locator('button[type="submit"]').click()

    await expect.poll(() => log(page)).toEqual(['void'])
    await settled(page)
    expect(await log(page)).toEqual(['void'])
  })

  test('a delete row with no handler of its own calls delete() after Accept, in danger', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('delete').click()

    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-danger-fg/)
    await dialog(page).locator('button[type="submit"]').click()

    await expect.poll(() => log(page)).toEqual(['delete'])
  })

  test('an href action navigates only after Accept', async ({ page }) => {
    await open(page)
    await openMenu(page)
    await page.getByTestId('leave').click()

    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await settled(page)
    expect(new URL(page.url()).pathname).toBe('/atom/e2e/actions')

    await dialog(page).locator('button[type="submit"]').click()
    await expect(page.getByTestId('landing')).toBeVisible()
  })

  test('a visible action calls once and asks nothing', async ({ page }) => {
    await open(page)
    await page.getByTestId('edit').click()
    await page.getByTestId('send').click()

    await expect.poll(() => log(page)).toEqual(['edit', 'send'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })
})

test.describe('a morph', () => {
  test('that adds an action moves the last visible one into the open ⋯, keeps it open and fires no call', async ({ page }) => {
    await open(page)
    await openMenu(page)
    expect(await rows(page)).toEqual(['Duplicate', '|', 'Void invoice', 'Archive and leave', 'Retire invoice', 'Delete invoice'])

    await page.evaluate(() => window.Livewire.first().call('addReminder'))
    await expect.poll(() => placement(page)).toEqual(['Send reminder:visible', 'Edit:visible', 'menu', 'Send invoice:primary'])

    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
    await expect(trigger(page)).toHaveAttribute('aria-expanded', 'true')
    expect(await rows(page)).toEqual(['Print', 'Duplicate', '|', 'Void invoice', 'Archive and leave', 'Retire invoice', 'Delete invoice'])
    expect(await log(page)).toEqual([])
  })

  test('keeps the focus on a visible button, and the actions where they were', async ({ page }) => {
    await open(page)
    await page.getByTestId('print').focus()

    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText('1')

    await expect(page.getByTestId('print')).toBeFocused()
    expect(await placement(page)).toEqual(['Edit:visible', 'Print:visible', 'menu', 'Send invoice:primary'])
    expect(await log(page)).toEqual([])
  })

  test('re-rendering with the ⋯ open leaves it open and the order the same', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText('1')

    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
    expect(await rows(page)).toEqual(['Duplicate', '|', 'Void invoice', 'Archive and leave', 'Retire invoice', 'Delete invoice'])
  })
})
