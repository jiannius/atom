import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The table's `header` and `checked` slots are server-rendered Blade, and they have to follow the server. Alpine 3.17 (Livewire 4.4)
// skips what a `<template x-if>` rendered when it morphs, so a slot wrapped in one froze after the first load: a host's `@if` never
// appeared, a select that depends on another filter never came. The bars are real elements shown with x-show now.
// tests/Fixtures/TableServerSlotsFixture.php, on the consumer's Tailwind, floor and latest.

const header = (page) => page.locator('[data-atom-table] > div:has([data-testid="header-input"])')
const bar = (page) => page.locator('[data-atom-table-checked]')
const tick = (page, n = 0) => page.locator('[data-atom-table-row] [data-atom-table-checkbox]').nth(n)
const checkedCount = (page) => page.locator('[data-atom-table-checked] span[x-text]').first()
const region = (page) => page.locator('[data-select="region"] select')
const city = (page) => page.locator('[data-select="city"] select')

const roundTrip = async (page, action) => {
  const response = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
  await action()
  await response
  await page.evaluate(() => new Promise((done) => setTimeout(done, 50)))
}

for (const tw of ['floor', 'latest']) {
  test.describe(`table slots follow the server, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 800 } })

    test('the run is on Alpine 3.17 or later (what skips an x-if\'s render during a morph)', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      const [major, minor] = (await page.evaluate(() => window.Alpine.version)).split('.').map(Number)

      expect(major).toBe(3)
      expect(minor).toBeGreaterThanOrEqual(17)
    })

    test('an @if in the header slot appears when the server turns it on', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await expect(page.getByTestId('header-input')).toBeVisible()
      await expect(page.locator('#x')).toHaveCount(0)

      await roundTrip(page, () => page.locator('[data-set-foo]').click())
      await expect(page.locator('#x')).toBeVisible()
      await expect(page.locator('#x')).toHaveText('foo is on')
    })

    test('a select that depends on another filter appears after the first is picked, with that region\'s options', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await expect(city(page)).toHaveCount(0)

      await roundTrip(page, () => region(page).selectOption('north'))
      await expect(city(page)).toBeVisible()
      await expect(city(page).locator('option', { hasText: 'Northtown' })).toHaveCount(1)

      await roundTrip(page, () => region(page).selectOption('south'))
      await expect(city(page).locator('option', { hasText: 'Southtown' })).toHaveCount(1)
      await expect(city(page).locator('option', { hasText: 'Northtown' })).toHaveCount(0)
    })

    test('the selection bar\'s slot follows the server while rows are ticked', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await expect(bar(page)).toBeHidden()
      await tick(page).click()
      await expect(bar(page)).toBeVisible()
      await expect(page.getByTestId('checked-note')).toHaveText('first note')
      await expect(page.getByTestId('checked-foo')).toHaveCount(0)

      await roundTrip(page, () => page.locator('[data-set-note]').click())
      await expect(page.getByTestId('checked-note')).toHaveText('changed note')

      await roundTrip(page, () => page.locator('[data-set-foo]').click())
      await expect(page.getByTestId('checked-foo')).toBeVisible()
      // still showing, and the selection survived the round trips
      await expect(bar(page)).toBeVisible()
      await expect(bar(page)).toContainText('1')
    })

    test('while rows are ticked the header is not on screen and cannot be focused; unticked, it is back and current', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await tick(page).click()
      await expect(header(page)).toBeHidden()
      expect(await page.getByTestId('header-input').evaluate((el) => { el.focus(); return document.activeElement === el })).toBe(false)

      // the server changes under the hidden header
      await roundTrip(page, () => page.locator('[data-set-note]').click())
      await roundTrip(page, () => page.locator('[data-set-foo]').click())

      // the server's changes did not bring the header back while rows are ticked
      await expect(header(page)).toBeHidden()
      await expect(bar(page)).toBeVisible()

      await tick(page).click()
      await expect(bar(page)).toBeHidden()
      await expect(header(page)).toBeVisible()
      await expect(page.getByTestId('header-input')).toHaveValue('changed note')
      await expect(page.locator('#x')).toBeVisible()
    })

    test('a sticky-selection table keeps its header while rows are ticked, and it still follows the server', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw, extra: '&sticky=1' })

      await tick(page).click()
      await expect(bar(page)).toBeVisible()
      await expect(page.getByTestId('header-input')).toBeVisible()

      await roundTrip(page, () => page.locator('[data-set-foo]').click())
      await expect(page.locator('#x')).toBeVisible()
      await expect(page.getByTestId('checked-foo')).toBeVisible()
    })

    test('the toolbar\'s ⋯ menu: opens with the host\'s items, follows the server, and an item\'s wire:click runs', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw, extra: '&menu=1' })

      const trigger = page.getByTestId('menu-toolbar').getByRole('button', { name: 'More actions' })
      const menu = page.getByTestId('menu-toolbar').getByRole('menu')

      await expect(trigger).toBeVisible()
      await expect(menu).toBeHidden()

      await trigger.click()
      await expect(menu).toBeVisible()
      await expect(menu.getByRole('menuitem')).toHaveCount(1)
      await expect(page.getByTestId('menu-foo')).toHaveCount(0)

      // a server @if inside the slot appears after a round trip (the header is a real element)
      await page.keyboard.press('Escape')
      await expect(menu).toBeHidden()
      await roundTrip(page, () => page.locator('[data-set-foo]').click())
      await trigger.click()
      await expect(page.getByTestId('menu-foo')).toBeVisible()
      await expect(menu.getByRole('menuitem')).toHaveCount(2)

      // an item's wire:click runs on the server
      await roundTrip(page, () => page.getByTestId('menu-note').click())
      await expect(page.getByTestId('header-input')).toHaveValue('from the menu')
    })

    test('select all matching: the button names the table\'s total, and the count reads it', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await tick(page).click()
      await expect(page.locator('[data-atom-table-select-all]')).toContainText('12')
      await expect(page.locator('[data-atom-table-select-all]')).toBeVisible()
    })

    test('the total follows the server while rows are ticked: a search narrows it and the select-all offer goes, clearing it brings the offer back, a bigger set names the new total', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw, extra: '&sticky=1' })

      const offer = page.locator('[data-atom-table-select-all]')

      for (const n of [0, 1, 2]) await tick(page, n).click()

      await expect(checkedCount(page)).toHaveText('3')
      await expect(offer).toBeVisible()
      await expect(offer).toContainText('12')

      // 12 -> 3: the three ticked rows are all of them, so there is nothing more to select
      await roundTrip(page, () => page.locator('[data-atom-table-search] input').fill('a'))
      await expect(offer).toBeHidden()
      await expect(checkedCount(page)).toHaveText('3')

      // 3 -> 12: the offer is back, naming 12
      await roundTrip(page, () => page.locator('[data-atom-table-search] input').fill(''))
      await expect(offer).toBeVisible()
      await expect(offer).toContainText('12')

      // 12 -> 20
      await roundTrip(page, () => page.locator('[data-set-bigger]').click())
      await expect(offer).toBeVisible()
      await expect(offer).toContainText('20')
    })

    test('select all matching: the count reads the total, and follows it when the server changes it', async ({ page }) => {
      await open(page, { path: 'table-server-slots', tw })

      await tick(page).click()
      await roundTrip(page, () => page.locator('[data-atom-table-select-all]').click())
      await expect(checkedCount(page)).toHaveText('12')

      await roundTrip(page, () => page.locator('[data-set-bigger]').click())
      await expect(checkedCount(page)).toHaveText('20')
    })

    test('nothing flashes at load: the header is in the server\'s HTML as it was drawn (not cloaked), the bar is cloaked until Alpine says a row is ticked', async ({ page }) => {
      const html = await (await page.request.get('/atom/e2e/table-server-slots?tw=' + tw)).text()

      expect(html).toMatch(/x-show="!\$wire\._table\.checkboxes\.length"(?! x-cloak)/)
      expect(html).toMatch(/x-show="\$wire\._table\.checkboxes\.length \|\| \$wire\._table\.select_all" x-cloak/)

      await open(page, { path: 'table-server-slots', tw })

      await expect(bar(page)).toBeHidden()
      expect(await bar(page).evaluate((el) => getComputedStyle(el).display)).toBe('none')
      await expect(header(page)).toBeVisible()
    })
  })
}
