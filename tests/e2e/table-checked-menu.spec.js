import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { sweepText, gate, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// The table's `checked-menu` slot (fixture: tests/Fixtures/TableCheckedFixture.php, `?menu=1`): the ⋯ in the bulk bar, last in the
// group after the host's buttons, with a delete item that counts the selection, an open menu that survives a morph, and legible in a
// `tone="accent"` bar. Measured on the consumer's Tailwind (`node tests/consumer/build.mjs`).

const bar = (page) => page.locator('[data-atom-table-checked]')
const more = (page) => bar(page).getByRole('button', { name: 'More actions' })
const menu = (page) => bar(page).locator('[data-atom-menu][popover]')
const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')

const show = async (page, { tw = 'latest', dark = false, accent = null, tone = null, buttons = 1, frameless = false, click = false } = {}) => {
  await open(page, { path: 'table-checked', tw, dark, accent, extra: `&menu=1&delete=1${click ? '&menuclick=1' : ''}&buttons=${buttons}${tone ? `&tone=${tone}` : ''}${frameless ? '&frameless=1' : ''}` })
  await page.waitForFunction(() => window.Livewire && window.Alpine)
  await expect(bar(page)).toBeVisible()
}

test('the ⋯ is the last thing in the bar\'s group, after the host\'s buttons and the delete', async ({ page }) => {
  await show(page, { buttons: 2 })

  await expect(more(page)).toBeVisible()
  const order = await bar(page).locator(':scope > div:last-child > *').evaluateAll((els) => els.map((el) => (
    el.matches('[data-atom-dropdown]') ? 'more' : el.innerText.trim()
  )))
  expect(order).toEqual(['Send reminders', 'Send reminders', 'Delete', 'more'])

  // a ghost button at the control height, like the toolbar's
  await expect(more(page)).toHaveClass(/h-control/)
  await expect(more(page)).toHaveClass(/bg-transparent/)
})

test('the ⋯ opens its items, and Escape closes it', async ({ page }) => {
  await show(page)

  await more(page).click()
  await expect(menu(page)).toBeVisible()
  await expect(menu(page).getByRole('menuitem', { name: 'Export' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(menu(page)).toBeHidden()
})

test('a delete item asks with the count, and says how many are ticked', async ({ page }) => {
  await show(page)
  await page.getByRole('checkbox', { name: 'Select row' }).nth(1).click()
  await page.getByRole('checkbox', { name: 'Select row' }).nth(2).click()
  await expect(bar(page)).toContainText('3 Selected')

  await more(page).click()
  await menu(page).getByRole('menuitem', { name: 'Delete' }).click()

  await expect(dialog(page)).toHaveAttribute('data-open', '')
  await expect(dialog(page).locator('.text-lg')).toHaveText('Delete 3 records?')
  await dialog(page).getByRole('button', { name: 'Cancel' }).click()
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
})

test('accepting a delete item runs the component\'s delete() for the selection and clears the bar', async ({ page }) => {
  await show(page)
  await page.getByRole('checkbox', { name: 'Select row' }).nth(1).click()
  await page.getByRole('checkbox', { name: 'Select row' }).nth(2).click()
  await expect(bar(page)).toContainText('3 Selected')
  await expect(page.locator('[data-deleted]')).toHaveText('0')

  await more(page).click()
  await menu(page).getByRole('menuitem', { name: 'Delete' }).click()
  await expect(dialog(page).locator('.text-lg')).toHaveText('Delete 3 records?')
  await dialog(page).getByRole('button', { name: 'Delete' }).click()

  await expect(page.locator('[data-deleted]')).toHaveText('3')
  await expect(bar(page)).toBeHidden()
})

// the same item with a `wire:click` of its own: the click gate asks (Q14), counting the selection, and runs it once after Accept
test('a delete item with its own wire:click asks with the count, runs it once after Accept, and Cancel runs nothing', async ({ page }) => {
  await show(page, { click: true })
  await page.getByRole('checkbox', { name: 'Select row' }).nth(1).click()
  await page.getByRole('checkbox', { name: 'Select row' }).nth(2).click()
  await expect(bar(page)).toContainText('3 Selected')

  await more(page).click()
  await menu(page).getByRole('menuitem', { name: 'Delete' }).click()
  await expect(dialog(page)).toHaveAttribute('data-open', '')
  await expect(dialog(page).locator('.text-lg')).toHaveText('Delete 3 records?')
  expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
  await dialog(page).getByRole('button', { name: 'Cancel' }).click()
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  await page.waitForTimeout(400)
  await expect(page.locator('[data-deleted]')).toHaveText('0')

  await more(page).click()
  await menu(page).getByRole('menuitem', { name: 'Delete' }).click()
  await dialog(page).locator('button[type="submit"]').click()

  await expect(page.locator('[data-deleted]')).toHaveText('3')
  await page.waitForTimeout(400)
  await expect(page.locator('[data-deleted]')).toHaveText('3')
})

test('an open menu survives a morph of the table', async ({ page }) => {
  await show(page)

  await more(page).click()
  await expect(menu(page)).toBeVisible()

  const renders = page.locator('[data-renders]')
  const before = Number(await renders.textContent())
  await page.evaluate(() => window.Livewire.first().call('bump'))
  await expect(renders).toHaveText(String(before + 1))

  await expect(menu(page)).toBeVisible()
  expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  await expect(more(page)).toHaveAttribute('aria-expanded', 'true')
  await expect(menu(page).getByRole('menuitem', { name: 'Export' })).toBeVisible()
})

for (const tw of ['floor', 'latest']) {
  for (const dark of [false, true]) {
    for (const accent of [null, ...ACCENTS]) {
      test(`the ⋯ reads in a tone="accent" bar (${tw}, ${dark ? 'dark' : 'light'}, ${accent ?? 'the default accent'})`, async ({ page }) => {
        await show(page, { tw, dark, accent, tone: 'accent' })

        await gate(more(page).locator('svg').last(), GRAPHIC, 'color', 'the ⋯ icon')
        expect(await sweepText(page, '[data-atom-table-checked]', TEXT, 3)).toEqual([])
      })
    }
  }
}
