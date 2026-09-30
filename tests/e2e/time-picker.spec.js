import { test, expect } from '@playwright/test'

// Drives the demo at /atom/docs/time-picker (resources/views/docs/demos/time-picker/basic.blade.php).
//
// Regression cover for smgdms#156: the hour and minute inputs stepped their value on every
// click (x-on:click.stop="up('hr')", from fa59957), so clicking a field to edit it changed it,
// and type="number" ignored maxlength. A click now selects the field's text so typing replaces it.
const picker = (page) => page.locator('[x-data="timePicker()"]').first()
const hour = (page) => picker(page).getByLabel('Hour')
const minute = (page) => picker(page).getByLabel('Minute')
const meridiem = (page) => picker(page).getByLabel('AM or PM')

test('clicking the hour or minute does not change it', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('10')
  await minute(page).click()
  await page.keyboard.type('05')
  await page.keyboard.press('Tab')

  await expect(hour(page)).toHaveValue('10')
  await expect(minute(page)).toHaveValue('05')

  await hour(page).click()
  await expect(hour(page)).toHaveValue('10')
  await minute(page).click()
  await expect(minute(page)).toHaveValue('05')
})

test('clicking the minute, typing 30 and tabbing away sets 30', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('10')
  await minute(page).click()
  await page.keyboard.type('30')
  await page.keyboard.press('Tab')

  await expect(hour(page)).toHaveValue('10')
  await expect(minute(page)).toHaveValue('30')
})

test('the hour and minute are two-character text inputs', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  for (const field of [hour(page), minute(page)]) {
    await expect(field).toHaveAttribute('type', 'text')
    await expect(field).toHaveAttribute('inputmode', 'numeric')
    await expect(field).toHaveAttribute('maxlength', '2')
  }
})

test('arrow keys still step the fields, and clicking AM/PM still toggles it', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('10')
  // The model is lazy: the typed value commits on change, so tab out and back before stepping.
  await page.keyboard.press('Tab')
  await page.keyboard.press('Shift+Tab')
  await page.keyboard.press('ArrowUp')
  await expect(hour(page)).toHaveValue('11')

  // From the empty '--' state an arrow key starts at 1, not NaN.
  await minute(page).focus()
  await page.keyboard.press('ArrowUp')
  await expect(minute(page)).toHaveValue(/^\d+$/)

  await expect(meridiem(page)).toHaveValue('AM')
  await meridiem(page).click()
  await expect(meridiem(page)).toHaveValue('PM')
})
