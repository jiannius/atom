import { test, expect } from '@playwright/test'
import { zoneInstant } from '../../resources/js/helpers/zone.js'

// #218: with `time` off a picker never invents a time of day and never drops one it was given. A range writes the days picked
// (`2026-09-01 to 2026-09-30`), a single picker holding nothing or a plain date writes a plain date, and one holding an instant keeps
// its time of day. The browser's zone is never part of the value, so the same days are written in every zone (the old range wrote the
// browser's midnight and 23:59:59.999 as UTC instants, which dropped the zone).
//
// Drives /atom/e2e/date-picker-timezone (plain Alpine models, so the spec reads the value the picker wrote) in browsers on both sides of UTC,
// with the clock held at 2026-10-31T20:00Z: already 1 Nov in Kuala Lumpur and Kiritimati, still 31 Oct in New York and Pago Pago.

const KL = 'Asia/Kuala_Lumpur'
const ZONES = [KL, 'UTC', 'America/New_York', 'Pacific/Kiritimati', 'Pacific/Pago_Pago']
const FIXED = '2026-10-31T20:00:00Z'

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const input = (page, name) => probe(page, name).locator('input[type="text"]').first()
const out = (page, name) => probe(page, name).locator('[data-out]')

async function open (page, query = {}) {
  await page.clock.setFixedTime(new Date(FIXED))
  await page.goto('/atom/e2e/date-picker-timezone?' + new URLSearchParams(query))
  await page.waitForFunction(() => window.Alpine && document.querySelector('[data-atom-date-range]')?._x_dataStack)
}

async function pickRange (page, which, y, m, d, name = 'range') {
  const calendar = probe(page, name).locator('.pika-lendar').nth(which)
  if (!(await calendar.isVisible())) await input(page, name).click()
  await calendar.locator('.pika-select-year').selectOption(String(y))
  await calendar.locator('.pika-select-month').selectOption(String(m - 1))
  await calendar.locator(`td:not(.is-empty) button.pika-day[data-pika-day="${d}"]`).click()
}

async function pickDate (page, name, y, m, d) {
  const calendar = probe(page, name).locator('.pika-lendar')
  if (!(await calendar.isVisible())) await input(page, name).click()
  await calendar.locator('.pika-select-year').selectOption(String(y))
  await calendar.locator('.pika-select-month').selectOption(String(m - 1))
  await calendar.locator(`td:not(.is-empty) button.pika-day[data-pika-day="${d}"]`).click()
}

async function type (page, name, text) {
  const field = input(page, name)
  await field.click()
  await field.fill(text)
  await field.press('Enter')
}

async function preset (page, label) {
  await input(page, 'range').click()
  await probe(page, 'range').getByText(label, { exact: true }).click()
}

const pad = (n) => String(n).padStart(2, '0')
const ymd = (ms) => { const d = new Date(ms); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}` }
const day = (y, m, d) => Date.UTC(y, m - 1, d)

// The calendar date it is in a zone at the fixed instant, as UTC midnight, so the maths below has no daylight saving in it.
function todayIn (timezone) {
  const [y, m, d] = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(FIXED)).split('-').map(Number)

  return day(y, m, d)
}

// What each preset writes, from the date it is in the zone (the same rules as date-range.js `preset`).
function presets (timezone) {
  const t = new Date(todayIn(timezone))
  const [y, m] = [t.getUTCFullYear(), t.getUTCMonth()]
  const back = (n) => todayIn(timezone) - n * 86400000
  const lastMonthEnd = Date.UTC(y, m, 0)
  const lastYearEnd = Date.UTC(y, 0, 0)
  const range = (a, b) => `${ymd(a)} to ${ymd(b)}`

  return {
    'Today': range(back(0), back(0)),
    'Yesterday': range(back(1), back(1)),
    'Last 7 Days': range(back(6), back(0)),
    'Last 30 Days': range(back(29), back(0)),
    'Last 180 Days': range(back(179), back(0)),
    'This Month': range(Date.UTC(y, m, 1), Date.UTC(y, m + 1, 0)),
    'Last Month': range(Date.UTC(y, m - 1, 1), lastMonthEnd),
    'This Year': range(Date.UTC(y, 0, 1), Date.UTC(y, 11, 31)),
    'Last Year': range(Date.UTC(y - 1, 0, 1), lastYearEnd),
  }
}

const startOf = (timezone, y, m, d) => zoneInstant({ year: y, month: m, day: d }, timezone).toISOString()
const endOf = (timezone, y, m, d) => zoneInstant({ year: y, month: m, day: d, hour: 23, minute: 59, second: 59, millisecond: 999 }, timezone).toISOString()
const at = (timezone, y, m, d, hour, minute) => zoneInstant({ year: y, month: m, day: d, hour, minute }, timezone).toISOString()

for (const timezoneId of ZONES) {
  test.describe(`in a browser on ${timezoneId}`, () => {
    test.use({ timezoneId })

    test.describe('a range', () => {
      test('picking 1 to 30 September writes those days', async ({ page }) => {
        await open(page)
        await pickRange(page, 0, 2026, 9, 1)
        await pickRange(page, 1, 2026, 9, 30)

        await expect(out(page, 'range')).toHaveText('2026-09-01 to 2026-09-30')
        await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')
      })

      test('each preset writes the days of the zone', async ({ page }) => {
        await open(page)

        for (const [label, expected] of Object.entries(presets(timezoneId))) {
          await preset(page, label)
          await expect(out(page, 'range'), label).toHaveText(expected)
        }
      })

      test('a typed range writes its days, and so does one typed day', async ({ page }) => {
        await open(page)
        await type(page, 'range', '01/09/2026 - 30/09/2026')
        await expect(out(page, 'range')).toHaveText('2026-09-01 to 2026-09-30')
        await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')

        await type(page, 'range', '15/09/2026')
        await expect(out(page, 'range')).toHaveText('2026-09-15 to 2026-09-15')
      })

      test('an end before the start is put back', async ({ page }) => {
        await open(page)
        await type(page, 'range', '01/09/2026 - 30/09/2026')
        await type(page, 'range', '30/09/2026 - 01/09/2026')

        await expect(out(page, 'range')).toHaveText('2026-09-01 to 2026-09-30')
        await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')
      })

      test('an old instant range shows the right days and is not rewritten until it is edited', async ({ page }) => {
        const old = `${startOf(timezoneId, 2026, 9, 1)} to ${endOf(timezoneId, 2026, 9, 30)}`

        await open(page, { range: old })
        await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')
        await page.waitForTimeout(200)
        await expect(out(page, 'range')).toHaveText(old)

        // an edit of one end writes both as days, the other end staying the day it was
        await pickRange(page, 0, 2026, 9, 2)
        await expect(out(page, 'range')).toHaveText('2026-09-02 to 2026-09-30')
      })

      test('a seeded date end survives an edit of the start, and the start an edit of the end', async ({ page }) => {
        await open(page, { range: '2026-09-01 to 2026-09-30' })
        await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')

        await pickRange(page, 0, 2026, 9, 2)
        await expect(out(page, 'range')).toHaveText('2026-09-02 to 2026-09-30')

        await pickRange(page, 1, 2026, 9, 29)
        await expect(out(page, 'range')).toHaveText('2026-09-02 to 2026-09-29')
      })
    })

    test.describe('a single date', () => {
      test('an empty picker writes the day picked', async ({ page }) => {
        await open(page)
        await pickDate(page, 'date', 2026, 10, 31)

        await expect(out(page, 'date')).toHaveText('2026-10-31')
        await expect(input(page, 'date')).toHaveValue('31 Oct 2026')
      })

      test('an empty picker writes the day typed', async ({ page }) => {
        await open(page)
        await type(page, 'date', '31/10/2026')

        await expect(out(page, 'date')).toHaveText('2026-10-31')
        await expect(input(page, 'date')).toHaveValue('31 Oct 2026')
      })

      test('a picker holding a date writes a date, picked or typed', async ({ page }) => {
        await open(page, { date: '2026-10-01' })
        await expect(input(page, 'date')).toHaveValue('01 Oct 2026')

        await pickDate(page, 'date', 2026, 10, 15)
        await expect(out(page, 'date')).toHaveText('2026-10-15')

        await type(page, 'date', '20/10/2026')
        await expect(out(page, 'date')).toHaveText('2026-10-20')
      })

      test('a picker holding an instant keeps its time of day, picked or typed', async ({ page }) => {
        await open(page, { date: at(timezoneId, 2026, 10, 1, 9, 30) })
        await expect(input(page, 'date')).toHaveValue('01 Oct 2026')

        await pickDate(page, 'date', 2026, 10, 15)
        await expect(out(page, 'date')).toHaveText(at(timezoneId, 2026, 10, 15, 9, 30))

        await type(page, 'date', '20/10/2026')
        await expect(out(page, 'date')).toHaveText(at(timezoneId, 2026, 10, 20, 9, 30))
      })

      test('clearing the text empties it, and the next day typed is a date again', async ({ page }) => {
        await open(page, { date: '2026-10-01' })
        await type(page, 'date', '')
        await expect(out(page, 'date')).toHaveText('')

        await type(page, 'date', '05/10/2026')
        await expect(out(page, 'date')).toHaveText('2026-10-05')
      })
    })

    test.describe('with time on, nothing changes', () => {
      test('a range still writes the first and last instant of each day', async ({ page }) => {
        await open(page)
        await type(page, 'range-time', '01/09/2026 - 30/09/2026')

        // the time pickers nested in the panel settle the seconds, so the end is compared to the minute
        await expect(out(page, 'range-time')).toHaveText(new RegExp(`^${startOf(timezoneId, 2026, 9, 1)} to ${endOf(timezoneId, 2026, 9, 30).slice(0, 16)}`))
      })

      test('a date and time still writes an instant', async ({ page }) => {
        await open(page)
        await type(page, 'date-time', '01/09/2026 09:30')

        await expect(out(page, 'date-time')).toHaveText(at(timezoneId, 2026, 9, 1, 9, 30))
      })
    })
  })
}

test.describe('the timezone prop, in a browser on UTC', () => {
  test.use({ timezoneId: 'UTC' })

  test('Today is the zone\'s day, written as a date', async ({ page }) => {
    // 2026-10-31 20:00 UTC is already 1 Nov in Kuala Lumpur
    await open(page, { tz: KL })
    await preset(page, 'Today')

    await expect(out(page, 'range')).toHaveText('2026-11-01 to 2026-11-01')
    await expect(input(page, 'range')).toHaveValue('01 Nov 2026 to 01 Nov 2026')
  })

  test('a seeded date range and an old instant range read as the zone\'s days', async ({ page }) => {
    await open(page, { tz: KL, range: '2026-09-01 to 2026-09-30', date: '2026-09-01' })
    await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')
    await expect(input(page, 'date')).toHaveValue('01 Sep 2026')

    await pickRange(page, 0, 2026, 9, 2)
    await expect(out(page, 'range')).toHaveText('2026-09-02 to 2026-09-30')
    await page.keyboard.press('Escape')

    await pickDate(page, 'date', 2026, 9, 5)
    await expect(out(page, 'date')).toHaveText('2026-09-05')
  })

  test('an old instant range is the zone\'s days, and an edit keeps the end', async ({ page }) => {
    await open(page, { tz: KL, range: '2026-08-31T16:00:00.000Z to 2026-09-30T15:59:59.999Z' })
    await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 30 Sep 2026')

    await pickRange(page, 0, 2026, 9, 2)
    await expect(out(page, 'range')).toHaveText('2026-09-02 to 2026-09-30')
  })
})

// The calendar's title jump picker is a date-only picker: it holds the day it was given and hands back the day picked, west of UTC too.
test.describe('the calendar\'s jump picker, in a browser on America/New_York', () => {
  test.use({ timezoneId: 'America/New_York' })

  const title = (page) => page.locator('[data-probe="calendar"] [role="status"]:not([data-atom-calendar-announce])')

  async function openCalendar (page, query) {
    await page.goto('/atom/e2e/calendar?' + query)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
    await expect(page.locator('[data-atom-calendar-body]').first()).toBeVisible()
  }

  async function jump (page, y, m, d) {
    await page.locator('[data-probe="calendar"]').getByRole('button', { name: 'Choose date' }).click()
    await expect(page.locator('[data-probe="calendar"] .pika-lendar')).toBeVisible()
    await page.evaluate(([y, m, d]) => {
      const picker = document.querySelector('[data-probe="calendar"] [data-atom-date-picker]')

      window.Alpine.$data(picker).pikaday.gotoDate(new Date(y, m - 1, d))
    }, [y, m, d])
    await page.locator(`[data-probe="calendar"] .pika-button[data-pika-year="${y}"][data-pika-month="${m - 1}"][data-pika-day="${d}"]`).click()
  }

  test('lands on the day picked, and holds a plain date', async ({ page }) => {
    await openCalendar(page, 'view=week')

    expect(await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).jump)).toBe('2026-10-09')

    await jump(page, 2026, 10, 22)
    await expect(title(page)).toHaveText('19 – 25 October 2026')
    expect(await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-probe="calendar"]')).jump)).toBe('2026-10-22')
  })

  test('a jump to the first of a month is that month, not the one before', async ({ page }) => {
    await openCalendar(page, '')

    await jump(page, 2026, 12, 1)
    await expect(title(page)).toHaveText('December 2026')
  })
})
