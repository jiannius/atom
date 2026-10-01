import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/date-picker-typed
// (tests/Fixtures/DatePickerTypedFixture.php).
//
// humblebear#338: the date pickers' text inputs were `readonly`, so the calendar was the
// only way to pick a date. They now take a typed date (the display format, day-first
// numerics or ISO), committed on blur or Enter and synced to wire:model / .live. An
// entry that does not parse is put back to the last good value and never reaches the
// model. Every assertion on the model reads the value the SERVER echoed back.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const input = (page, name) => probe(page, name).locator('input[type="text"]').first()
const server = (page, name) => probe(page, name).locator('[data-server]')

// The ISO string the picker stores for a local date / time, as the browser computes it.
const iso = (page, y, m, d, h = 0, min = 0, s = 0, ms = 0) =>
  page.evaluate(([y, m, d, h, min, s, ms]) => new Date(y, m - 1, d, h, min, s, ms).toISOString(), [y, m, d, h, min, s, ms])

async function type (page, name, text, commit = 'Enter') {
  const field = input(page, name)
  await field.click()
  await field.fill(text)
  if (commit === 'Enter') await field.press('Enter')
  else await field.press('Tab')
}

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/date-picker-typed')
  await page.waitForFunction(() => window.Livewire && window.Alpine)
})

test('the inputs are no longer read-only', async ({ page }) => {
  await expect(input(page, 'date')).toBeEditable()
  await expect(input(page, 'range')).toBeEditable()
})

test('a typed day-first date reaches a .live model on Enter', async ({ page }) => {
  await type(page, 'date', '01/10/2024')

  await expect(server(page, 'date')).toHaveText(await iso(page, 2024, 10, 1))
  await expect(input(page, 'date')).toHaveValue('01 Oct 2024')
})

test('the display format, in any case, reaches the model on blur', async ({ page }) => {
  await type(page, 'date', '15 mar 2025', 'Tab')

  await expect(server(page, 'date')).toHaveText(await iso(page, 2025, 3, 15))
  await expect(input(page, 'date')).toHaveValue('15 Mar 2025')
})

test('an ISO date is accepted', async ({ page }) => {
  await type(page, 'date', '2024-12-31')
  await expect(server(page, 'date')).toHaveText(await iso(page, 2024, 12, 31))
})

test('an invalid or partial entry is put back and never reaches the model', async ({ page }) => {
  await type(page, 'date', '01/10/2024')
  const good = await iso(page, 2024, 10, 1)
  await expect(server(page, 'date')).toHaveText(good)

  for (const bad of ['01/10', '31/02/2024', 'next tuesday', '13/13/2024']) {
    await type(page, 'date', bad)
    await expect(input(page, 'date')).toHaveValue('01 Oct 2024')
  }

  await type(page, 'date', '01/1', 'Tab')
  await expect(input(page, 'date')).toHaveValue('01 Oct 2024')

  // Give any stray request time to land before asserting nothing changed.
  await page.waitForTimeout(300)
  await expect(server(page, 'date')).toHaveText(good)
})

test('clearing the text clears the model', async ({ page }) => {
  await type(page, 'date', '01/10/2024')
  await expect(server(page, 'date')).not.toBeEmpty()

  await type(page, 'date', '')
  await expect(server(page, 'date')).toBeEmpty()
})

test('Escape puts the typed text back and closes the calendar', async ({ page }) => {
  await type(page, 'date', '01/10/2024')
  const field = input(page, 'date')
  await field.click()

  const popover = probe(page, 'date').locator('[popover]')
  await expect.poll(() => popover.evaluate(el => el.matches(':popover-open'))).toBe(true)

  await field.fill('02/1')
  await field.press('Escape')

  await expect(field).toHaveValue('01 Oct 2024')
  await expect.poll(() => popover.evaluate(el => el.matches(':popover-open'))).toBe(false)
})

test('Tab reaches the input and typing works without opening anything first', async ({ page }) => {
  await page.locator('[data-before]').focus()
  await page.keyboard.press('Tab')
  await expect(input(page, 'date')).toBeFocused()

  await page.keyboard.type('05/06/2024')
  await page.keyboard.press('Enter')
  await expect(server(page, 'date')).toHaveText(await iso(page, 2024, 6, 5))
})

test('the calendar still picks a date, and follows a typed one', async ({ page }) => {
  await type(page, 'date', '01/10/2024')
  await input(page, 'date').click()

  const calendar = probe(page, 'date').locator('.pika-lendar')
  await expect(calendar.locator('.pika-select-month')).toHaveValue('9')
  await expect(calendar.locator('.pika-select-year')).toHaveValue('2024')

  await calendar.locator('button.pika-day[data-pika-day="15"]').click()
  await expect(server(page, 'date')).toHaveText(await iso(page, 2024, 10, 15))
  await expect(input(page, 'date')).toHaveValue('15 Oct 2024')
})

// Pikaday's keyboardInput listens on the document: once the input was typeable, a caret
// arrow with the calendar open moved the picked day and committed it.
test('caret keys in the input never move the picked day', async ({ page }) => {
  for (const name of ['date', 'date-time']) {
    await type(page, name, '01/10/2024')
    await expect(server(page, name)).not.toBeEmpty()
    const committed = await server(page, name).textContent()
    const shown = await input(page, name).inputValue()

    const field = input(page, name)
    await field.click()
    const popover = probe(page, name).locator('[popover]')
    await expect.poll(() => popover.evaluate(el => el.matches(':popover-open'))).toBe(true)

    for (const key of ['End', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'Home', 'Backspace']) {
      await field.press(key)
    }

    await page.waitForTimeout(300)
    await expect(server(page, name)).toHaveText(committed)
    expect(await popover.evaluate(el => el.matches(':popover-open'))).toBe(true)
    await field.press('Escape')
    await expect(field).toHaveValue(shown)
  }
})

test('ArrowDown in the input opens the calendar, for a single date and a range', async ({ page }) => {
  for (const name of ['date', 'range']) {
    const popover = probe(page, name).locator('[popover]')
    await input(page, name).focus()
    expect(await popover.evaluate(el => el.matches(':popover-open'))).toBe(false)

    await input(page, name).press('ArrowDown')
    await expect.poll(() => popover.evaluate(el => el.matches(':popover-open'))).toBe(true)
    await expect(probe(page, name).locator('.pika-lendar').first()).toBeVisible()

    await input(page, name).press('Escape')
    await expect.poll(() => popover.evaluate(el => el.matches(':popover-open'))).toBe(false)
  }
})

test('a time-flagged picker takes a typed date and time, and a bare date keeps the time', async ({ page }) => {
  await type(page, 'date-time', '01/10/2024 09:30 pm')
  await expect(server(page, 'date-time')).toHaveText(await iso(page, 2024, 10, 1, 21, 30))
  await expect(input(page, 'date-time')).toHaveValue('01 Oct 2024 09:30 PM')

  await type(page, 'date-time', '02/10/2024')
  await expect(server(page, 'date-time')).toHaveText(await iso(page, 2024, 10, 2, 21, 30))
})

test('a plain date picker refuses a time', async ({ page }) => {
  await type(page, 'date', '01/10/2024 09:30')
  await expect(input(page, 'date')).toHaveValue('')
  await page.waitForTimeout(300)
  await expect(server(page, 'date')).toBeEmpty()
})

test('a typed range reaches a .live model, with either separator', async ({ page }) => {
  const start = await iso(page, 2024, 10, 1)
  const end = await iso(page, 2024, 12, 31, 23, 59, 59, 999)

  await type(page, 'range', '01/10/2024 - 31/12/2024')
  await expect(server(page, 'range')).toHaveText(`${start} to ${end}`)
  await expect(input(page, 'range')).toHaveValue('01 Oct 2024 to 31 Dec 2024')

  await type(page, 'range', '01 Nov 2024 to 30 nov 2024', 'Tab')
  await expect(server(page, 'range')).toHaveText(
    `${await iso(page, 2024, 11, 1)} to ${await iso(page, 2024, 11, 30, 23, 59, 59, 999)}`,
  )
})

test('one typed date is a one-day range', async ({ page }) => {
  await type(page, 'range', '2024-10-01')
  await expect(server(page, 'range')).toHaveText(
    `${await iso(page, 2024, 10, 1)} to ${await iso(page, 2024, 10, 1, 23, 59, 59, 999)}`,
  )
})

test('an invalid range is put back and never reaches the model', async ({ page }) => {
  await type(page, 'range', '01/10/2024 - 31/12/2024')
  await expect(input(page, 'range')).toHaveValue('01 Oct 2024 to 31 Dec 2024')
  await expect(server(page, 'range')).toContainText(' to ')
  const good = await server(page, 'range').textContent()

  for (const bad of ['01/10/2024 - ', '01/10/2024 - 31/12', '31/12/2024 - 01/10/2024', 'a - b - c']) {
    await type(page, 'range', bad)
    await expect(input(page, 'range')).toHaveValue('01 Oct 2024 to 31 Dec 2024')
  }

  await page.waitForTimeout(300)
  await expect(server(page, 'range')).toHaveText(good)
})

test('a typed range moves the open calendars', async ({ page }) => {
  await type(page, 'range', '01/10/2024 - 31/12/2024')
  await input(page, 'range').click()

  const calendars = probe(page, 'range').locator('[data-atom-date-picker-calendar]')
  await expect(calendars.nth(0).locator('.pika-select-month')).toHaveValue('9')
  await expect(calendars.nth(1).locator('.pika-select-month')).toHaveValue('11')
  await expect(calendars.nth(0).locator('.pika-lendar')).toHaveCount(1)
})

test('typed values on deferred wire:model reach the server on submit', async ({ page }) => {
  // Tab commits; the calendar the click opened stays up until dismissed (Escape here,
  // a click elsewhere for a mouse user), as it always has.
  await type(page, 'deferred-date', '01/10/2024', 'Tab')
  await page.keyboard.press('Escape')
  await type(page, 'deferred-range', '01/10/2024 - 31/12/2024', 'Tab')
  await page.keyboard.press('Escape')

  await page.locator('[data-save]').click()
  await expect(page.locator('[data-saves]')).toHaveText('1')

  await expect(server(page, 'deferred-date')).toHaveText(await iso(page, 2024, 10, 1))
  await expect(server(page, 'deferred-range')).toHaveText(
    `${await iso(page, 2024, 10, 1)} to ${await iso(page, 2024, 12, 31, 23, 59, 59, 999)}`,
  )
})

test('Enter commits an edit without submitting the form; Enter again submits', async ({ page }) => {
  await type(page, 'deferred-date', '01/10/2024')
  await page.waitForTimeout(300)
  await expect(page.locator('[data-saves]')).toHaveText('0')

  await input(page, 'deferred-date').press('Enter')
  await expect(page.locator('[data-saves]')).toHaveText('1')
  await expect(server(page, 'deferred-date')).toHaveText(await iso(page, 2024, 10, 1))
})
