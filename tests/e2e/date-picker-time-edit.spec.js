import { test, expect } from '@playwright/test'
import { caseOf, openCase } from './disabled-helpers.js'

// Issue #192: the date picker with a time handed Pikaday "YYYY-MM-DD HH:mm:ss", a string some WebKit builds cannot parse (the one on a Mac reads it, the one
// CI runs does not), so the calendar did not show the picked day and a typed date did not move it. Pikaday is now given a Date built from the picker's
// numbers. This spec opens the panel, picks a day and types a date, and reads what the model and the calendar hold, and runs in WebKit too (see
// `disabledRigSpecs` in playwright.config.js). It also makes `Date.parse` refuse that string in every engine, as the strict WebKit does, so it fails on the
// string in Chromium as well and does not depend on the build it runs in.

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => {
    const parse = Date.parse
    Date.parse = (text) => (typeof text === 'string' && /^\d{4}-\d{2}-\d{2} \d/.test(text) ? NaN : parse(text))
  })
})

const def = caseOf('date_picker_time')
const input = (page) => page.locator(`[data-case="${def.id}"] input[type="text"]`).first()
const model = (page) => page.evaluate(() => window.Livewire.first().get('v'))

test('date picker with a time: the panel opens on the selected day without an error', async ({ page }) => {
  await openCase(page, def)
  await input(page).click()

  const selected = page.locator(`[data-case="${def.id}"] .pika-table td.is-selected button`)
  await expect(selected).toHaveCount(1)
  await expect(selected).toHaveText('4')
  expect(page.errors).toEqual([])
})

test('date picker with a time: picking a day writes a real date and keeps the time', async ({ page }) => {
  await openCase(page, def)
  const before = await model(page)
  await input(page).click()
  await page.locator(`[data-case="${def.id}"] .pika-table td:not(.is-disabled):not(.is-selected) button`).first().click()

  await expect.poll(() => model(page)).not.toBe(before)
  expect(await model(page)).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:30:00\.000Z$/)
  expect(page.errors).toEqual([])
})

test('date picker with a time: a date typed into the open panel moves the calendar to it', async ({ page }) => {
  await openCase(page, def)
  await input(page).click()
  await expect(page.locator(`[data-case="${def.id}"] .pika-table`)).toBeVisible()

  await input(page).press('ControlOrMeta+A')
  await page.keyboard.type('17 Mar 2026 10:30 AM')
  await page.keyboard.press('Tab')

  await expect(page.locator(`[data-case="${def.id}"] .pika-table td.is-selected button`)).toHaveText('17')
  await expect(page.locator(`[data-case="${def.id}"] .pika-label`).first()).toContainText('March')
  expect(page.errors).toEqual([])
})
