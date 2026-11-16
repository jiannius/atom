import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:action> in table.toolbar (tests/Fixtures/ActionTableFixture.php, resources/views/e2e/action-table.blade.php): the toolbar draws the first two actions as
// ghost buttons, the rest in its ⋯ (neutral, a separator, warning), and a button with an icon is icon-only below `sm` with its name still in the DOM for a screen
// reader. Measured on the consumer's Tailwind (`node tests/consumer/build.mjs`).

const toolbar = (page) => page.locator('[data-atom-table-toolbar-end]')
const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const more = (page) => toolbar(page).getByRole('button', { name: 'More actions' })
const menu = (page) => toolbar(page).locator('[data-atom-menu][popover]')

const show = async (page, width = 1280) => {
  await page.setViewportSize({ width, height: 800 })
  await open(page, { path: 'action-table' })
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const placement = (page) => toolbar(page).evaluate((el) => [...el.children].map((child) => child.hasAttribute('data-atom-action')
  ? `${child.innerText.replace(/\s+/g, ' ').trim()}:${child.getAttribute('data-atom-action')}`
  : child.hasAttribute('data-atom-dropdown') ? 'menu' : child.nodeName.toLowerCase()).filter((name) => name !== 'span'))

const rows = (page) => menu(page).evaluate((el) => [...el.children].map((child) => child.getAttribute('role') === 'separator' ? '|' : child.textContent.replace(/\s+/g, ' ').trim()))

test('two ghost buttons, then the ⋯; the rest in the menu after a separator', async ({ page }) => {
  await show(page)

  expect(await placement(page)).toEqual(['Export:visible', 'Import:visible', 'menu'])
  await expect(page.getByTestId('archive')).toHaveAttribute('data-atom-action', 'menu')

  await more(page).click()
  await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  expect(await rows(page)).toEqual(['Archive old', 'Print list', '|', 'Void all'])

  // a ghost button at the control height
  await expect(page.getByTestId('export')).toHaveClass(/bg-transparent/)
  await expect(page.getByTestId('export')).toHaveClass(/h-control/)
})

test('375px: an icon action is icon-only and keeps its name; 640px shows the label', async ({ page }) => {
  await show(page, 375)

  const exportButton = page.getByTestId('export')
  await expect(exportButton).toBeVisible()
  await expect(page.getByRole('button', { name: 'Export' })).toHaveCount(1)
  const narrow = await exportButton.evaluate((el) => {
    const label = el.querySelector('.max-sm\\:sr-only')
    const box = label.getBoundingClientRect()

    return { width: el.getBoundingClientRect().width, label: { w: box.width, h: box.height }, text: label.textContent.trim() }
  })

  expect(narrow.text).toBe('Export')
  expect(narrow.label.w).toBeLessThanOrEqual(1)
  expect(narrow.width).toBeLessThan(60)

  await page.setViewportSize({ width: 640, height: 800 })
  await expect.poll(async () => (await exportButton.evaluate((el) => el.querySelector('.max-sm\\:sr-only').getBoundingClientRect().width))).toBeGreaterThan(20)
  expect((await exportButton.boundingBox()).width).toBeGreaterThan(narrow.width + 20)
  expect(await placement(page)).toEqual(['Export:visible', 'Import:visible', 'menu'])
})

test('a visible action calls once and asks nothing', async ({ page }) => {
  await show(page)
  await page.getByTestId('export').click()
  await page.getByTestId('import').click()

  await expect.poll(() => log(page)).toEqual(['export', 'import'])
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
})

test('a warning row in the ⋯ asks in warning; Cancel makes no call, Accept makes exactly one', async ({ page }) => {
  await show(page)
  await more(page).click()
  await page.getByTestId('void-all').click()

  await expect(dialog(page)).toHaveAttribute('data-open', '')
  await expect(dialog(page)).toContainText('Void all?')
  await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
  await dialog(page).getByRole('button', { name: 'Cancel' }).click()
  await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 300)))
  expect(await log(page)).toEqual([])
  await expect(more(page)).toBeFocused()

  await more(page).click()
  await page.getByTestId('void-all').click()
  await dialog(page).locator('button[type="submit"]').click()
  await expect.poll(() => log(page)).toEqual(['void'])
})

test('Tab visits the visible actions, then the ⋯, in order', async ({ page }) => {
  await show(page)
  await page.getByTestId('export').focus()

  await page.keyboard.press('Tab')
  await expect(page.getByTestId('import')).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(more(page)).toBeFocused()
})

test('every action is drawn once and no marker reaches the page', async ({ page }) => {
  await show(page)

  for (const name of ['export', 'import']) {
    await expect(page.getByTestId(name)).toHaveCount(1)
  }

  expect(await page.content()).not.toContain('atom:action:')
})
