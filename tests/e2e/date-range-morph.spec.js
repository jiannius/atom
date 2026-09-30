import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/date-range-morph (tests/Fixtures/DateRangeMorphFixture.php).
//
// Regression cover for smgauto/smgdms#141: <atom:date-picker variant="range"> showed a 2x2
// grid of calendars (the bottom pair duplicating the top pair, both on the same
// un-navigated month) instead of one pair, after the dropdown reopened following certain
// Livewire re-renders.
//
// date-range.js's setCalendar() builds two `new Pikaday` and .prepend()s their `.el` into
// the two [data-atom-date-picker-calendar] containers, with no cleanup of a previous pair —
// unlike date-picker.js's single-date variant, which destroys + recreates its Pikaday on
// every dropdown open/close. Whenever setCalendar() runs a second time on the same node
// (the component's init() re-entering, or Alpine re-processing an already-populated node),
// the old grid is never removed before the new one is prepended.
//
// range.blade.php's root carries wire:ignore, which makes Livewire skip() the node during
// morph (vendor/livewire/livewire/dist/livewire.js ~14359) — a plain component re-render
// (Bump, or a .live round trip from picking a date) does NOT reach setCalendar() twice and
// does not reproduce the bug; confirmed empirically below as a non-regression baseline. The
// mechanism that does reproduce it is Alpine re-initialising the same DOM node whose
// children still hold the previous run's calendars — exercised directly here via
// Alpine.destroyTree()/initTree() (the real lifecycle a wire:ignore'd node can go through if
// it is ever reprocessed without the intervening DOM node being replaced), and via calling
// the component's own setCalendar() a second time, the literal code path the bug lived in.

const probe = (page) => page.locator('[data-probe="live"]')
const containers = (page) => probe(page).locator('[data-atom-date-picker-calendar]')
const grids = (page, i) => containers(page).nth(i).locator('.pika-lendar')

async function bump (page) {
  const renders = page.locator('[data-renders]')
  const before = Number(await renders.textContent())
  await page.locator('[data-bump]').click()
  await expect(renders).toHaveText(String(before + 1))
}

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/date-range-morph')
  await page.waitForFunction(() => window.Livewire && window.Alpine)
})

test('renders exactly one calendar per side on open', async ({ page }) => {
  await expect(grids(page, 0)).toHaveCount(1)
  await expect(grids(page, 1)).toHaveCount(1)
})

test('a plain Livewire re-render does not duplicate the calendars (wire:ignore holds)', async ({ page }) => {
  await bump(page)
  await expect(grids(page, 0)).toHaveCount(1)
  await expect(grids(page, 1)).toHaveCount(1)
})

test('a .live round trip from picking a preset does not duplicate the calendars', async ({ page }) => {
  await probe(page).locator('[data-atom-dropdown] > button').click()

  const [response] = await Promise.all([
    page.waitForResponse(r => r.url().includes('livewire')),
    page.getByRole('menuitem', { name: 'Today' }).click(),
  ])
  expect(response.ok()).toBe(true)
  await page.waitForTimeout(300)

  await probe(page).locator('[data-atom-dropdown] > button').click()
  await expect(grids(page, 0)).toHaveCount(1)
  await expect(grids(page, 1)).toHaveCount(1)
})

// The literal bug: setCalendar() prepended a fresh Pikaday pair without ever cleaning up
// the previous one.
test('calling setCalendar() again does not stack a second pair of grids', async ({ page }) => {
  await page.evaluate(() => {
    const root = document.querySelector('[data-probe="live"]')
    window.Alpine.$data(root).setCalendar()
  })

  await expect(grids(page, 0)).toHaveCount(1)
  await expect(grids(page, 1)).toHaveCount(1)
})

// A wire:ignore'd node re-processed by Alpine without the DOM node itself being replaced —
// the real lifecycle a duplicated grid came from, since destroyTree() only ran cleanups
// (never emptied the calendar containers) before initTree() ran setCalendar() again.
test('a destroy + re-init cycle on the same node leaves exactly one pair of grids', async ({ page }) => {
  await page.evaluate(() => {
    const root = document.querySelector('[data-probe="live"]')
    window.Alpine.destroyTree(root)
    window.Alpine.initTree(root)
  })

  await expect(grids(page, 0)).toHaveCount(1)
  await expect(grids(page, 1)).toHaveCount(1)

  // not just "no duplicate" — the recreated pair is still live and independently
  // navigable, not orphaned markup left over from the destroyed instance
  await probe(page).locator('[data-atom-dropdown] > button').click()
  const endNext = containers(page).nth(1).locator('button.pika-next')
  const startMonthBefore = await containers(page).nth(0).locator('.pika-select-month').inputValue()
  await endNext.click()
  const endMonthAfter = await containers(page).nth(1).locator('.pika-select-month').inputValue()
  const startMonthAfter = await containers(page).nth(0).locator('.pika-select-month').inputValue()

  expect(startMonthAfter).toBe(startMonthBefore)
  expect(Number(endMonthAfter)).toBe((Number(startMonthBefore) + 1) % 12)
})
