import { test, expect } from '@playwright/test'

// Drives the OTP demo on /atom/docs/input. The first otp instance is wrapped
// in an x-on:otp-completed listener that mirrors the code into [data-atom-otp-result].
const firstOtp = (page) => page.locator('[data-atom-input-otp]').first()

test('auto-advances while typing and dispatches otp-completed when full', async ({ page }) => {
  await page.goto('/atom/docs/input')

  const otp = firstOtp(page)
  const boxes = otp.locator('[data-atom-input-otp-box]')
  const result = page.locator('[data-atom-otp-result]').first()

  await boxes.nth(0).focus()
  await page.keyboard.type('123456')

  await expect(boxes.nth(0)).toHaveValue('1')
  await expect(boxes.nth(5)).toHaveValue('6')
  // focus landed on the last box, and completion fired
  await expect(boxes.nth(5)).toBeFocused()
  await expect(result).toHaveText('123456')
})

test('keeps only one digit per box', async ({ page }) => {
  await page.goto('/atom/docs/input')

  const boxes = firstOtp(page).locator('[data-atom-input-otp-box]')

  await boxes.nth(0).focus()
  await page.keyboard.type('7')
  await expect(boxes.nth(0)).toHaveValue('7')
  await expect(boxes.nth(1)).toBeFocused()
})

test('backspace on an empty box moves focus to the previous one', async ({ page }) => {
  await page.goto('/atom/docs/input')

  const boxes = firstOtp(page).locator('[data-atom-input-otp-box]')

  await boxes.nth(0).focus()
  await page.keyboard.type('12') // fills boxes 0,1 and focuses box 2 (empty)
  await expect(boxes.nth(2)).toBeFocused()

  await page.keyboard.press('Backspace')
  await expect(boxes.nth(1)).toBeFocused()
})

test('paste fills every box from the clipboard', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  await page.goto('/atom/docs/input')

  const boxes = firstOtp(page).locator('[data-atom-input-otp-box]')
  const result = page.locator('[data-atom-otp-result]').first()

  await page.evaluate(() => navigator.clipboard.writeText('654321'))
  await boxes.nth(0).focus()
  await page.keyboard.press('ControlOrMeta+v')

  await expect(boxes.nth(0)).toHaveValue('6')
  await expect(boxes.nth(5)).toHaveValue('1')
  await expect(result).toHaveText('654321')
})
