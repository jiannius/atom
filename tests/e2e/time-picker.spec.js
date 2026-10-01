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

// The hour and minute accept digits only. A plain `+5` used to reach dayjs and leave the
// string 'Invalid Date' in the bound value (and a RangeError from .toISOString() when the
// picker sits inside a date-picker with `time`).
const boundValue = (page) => picker(page).evaluate((el) => window.Alpine.$data(el).timePickerValue)

function collectPageErrors (page) {
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))
  return errors
}

for (const [typed, expected] of [['+5', '5'], ['5.', '5'], ['ab', ''], ['-5', '5']]) {
  test(`typing "${typed}" into the hour leaves only its digits`, async ({ page }) => {
    const errors = collectPageErrors(page)
    await page.goto('/atom/docs/time-picker')

    await hour(page).click()
    await page.keyboard.type(typed)
    await expect(hour(page)).toHaveValue(expected)

    await minute(page).click()
    await page.keyboard.type('30')
    await page.keyboard.press('Tab')

    expect(String(await boundValue(page))).not.toContain('Invalid')
    expect(errors).toEqual([])
  })
}

test('pasting +5 into the hour leaves 5', async ({ page }) => {
  const errors = collectPageErrors(page)
  await page.goto('/atom/docs/time-picker')

  await hour(page).fill('+5')
  await expect(hour(page)).toHaveValue('5')

  await minute(page).fill('+9')
  await expect(minute(page)).toHaveValue('9')

  await page.keyboard.press('Tab')
  expect(String(await boundValue(page))).not.toContain('Invalid')
  expect(errors).toEqual([])
})

test('fullwidth digits are folded to ASCII', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('１０')
  await expect(hour(page)).toHaveValue('10')
})

test('arrow keys step from what was typed, not from the last committed value', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('10')
  await page.keyboard.press('ArrowUp')
  await expect(hour(page)).toHaveValue('11')
  await page.keyboard.press('ArrowDown')
  await expect(hour(page)).toHaveValue('10')

  await minute(page).click()
  await page.keyboard.type('30')
  await page.keyboard.press('ArrowDown')
  await expect(minute(page)).toHaveValue('29')
})

test('a full time is still written to the bound value in plain time mode', async ({ page }) => {
  await page.goto('/atom/docs/time-picker')

  await hour(page).click()
  await page.keyboard.type('10')
  await minute(page).click()
  await page.keyboard.type('05')
  await page.keyboard.press('Tab')

  expect(await boundValue(page)).toBe('10:05:00')
})

test('setTime() never lets a non-numeric value reach dayjs, even if one is set directly', async ({ page }) => {
  const errors = collectPageErrors(page)
  await page.goto('/atom/docs/time-picker')

  await picker(page).evaluate((el) => {
    const data = window.Alpine.$data(el)
    data.hr = '+5'
    data.min = 'ab'
  })
  await page.waitForTimeout(100)
  expect(await boundValue(page)).toBeNull()

  await picker(page).evaluate((el) => { window.Alpine.$data(el).min = '+7' })
  await expect.poll(() => boundValue(page)).toBe('05:07:00')
  expect(errors).toEqual([])
})
