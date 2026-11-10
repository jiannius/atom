import { test, expect } from '@playwright/test'
import { caseOf, openCase } from './disabled-helpers.js'

// Issue #191: every edit built dayjs("1970-01-01 7:30 AM"), a string WebKit's Date cannot parse, and the model got "Invalid Date" on the first edit.
// The picker now builds the time from its numbers. This spec reads what the model holds after each kind of edit, and runs in WebKit too (see
// `disabledRigSpecs` in playwright.config.js).

const def = caseOf('time_picker')
const hour = (page) => page.locator(`[data-case="${def.id}"] input[aria-label="Hour"]`)
const minute = (page) => page.locator(`[data-case="${def.id}"] input[aria-label="Minute"]`)
const meridiem = (page) => page.locator(`[data-case="${def.id}"] input[aria-label="AM or PM"]`)
const model = (page) => page.evaluate(() => window.Livewire.first().get('v'))

test('time-picker: typing the hour, the minute and AM/PM writes a real time', async ({ page }) => {
  await openCase(page, def)
  await expect(hour(page)).toHaveValue('10')
  await expect(meridiem(page)).toHaveValue('AM')

  await hour(page).click()
  await page.keyboard.press('ControlOrMeta+A')
  await page.keyboard.type('7')
  await page.keyboard.press('Tab')
  await expect.poll(() => model(page)).toBe('07:30:00')

  await meridiem(page).click()
  await expect.poll(() => model(page)).toBe('19:30:00')

  await minute(page).click()
  await page.keyboard.press('ArrowUp')
  await expect.poll(() => model(page)).toBe('19:31:00')
  await expect(hour(page)).toHaveValue('07')
  await expect(meridiem(page)).toHaveValue('PM')
  expect(page.errors).toEqual([])
})

test('time-picker: 12 AM is midnight and 12 PM is noon', async ({ page }) => {
  await openCase(page, def)

  await hour(page).click()
  await page.keyboard.press('ControlOrMeta+A')
  await page.keyboard.type('12')
  await page.keyboard.press('Tab')
  await expect.poll(() => model(page)).toBe('00:30:00')

  await meridiem(page).click()
  await expect.poll(() => model(page)).toBe('12:30:00')
})

test('time-picker: a server value is shown, in both halves of the day', async ({ page }) => {
  await openCase(page, def, { value: '00:05:00' })
  await expect(hour(page)).toHaveValue('12')
  await expect(minute(page)).toHaveValue('05')
  await expect(meridiem(page)).toHaveValue('AM')

  await openCase(page, def, { value: '23:45:00' })
  await expect(hour(page)).toHaveValue('11')
  await expect(minute(page)).toHaveValue('45')
  await expect(meridiem(page)).toHaveValue('PM')
})
