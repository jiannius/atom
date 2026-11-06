import { test, expect } from '@playwright/test'
import dayjs from 'dayjs'
import { validZone, zoneInstant, zoneOf, zoneParts } from '../../resources/js/helpers/zone.js'

// The `timezone` prop of <atom:date-picker> (helpers/zone.js): the pickers read and write the day in the APP's zone, not the browser's.
// Drives /atom/e2e/date-picker-timezone (resources/views/e2e/date-picker-timezone.blade.php), with the browser held in UTC so that
// "the browser's day" and "the zone's day" are far apart. Without the prop, the picker stays on the browser's day.

const KL = 'Asia/Kuala_Lumpur'
const NY = 'America/New_York'

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const input = (page, name) => probe(page, name).locator('input[type="text"]').first()
const out = (page, name) => probe(page, name).locator('[data-out]')

async function open (page, query = {}, fixed = '2026-10-31T20:00:00Z') {
  await page.clock.setFixedTime(new Date(fixed))
  await page.goto('/atom/e2e/date-picker-timezone?' + new URLSearchParams(query))
  await page.waitForFunction(() => window.Alpine && document.querySelector('[data-atom-date-range]')?._x_dataStack)
}

// Open the range's panel and click a day on its start (0) or end (1) calendar.
async function pickRange (page, which, y, m, d) {
  const calendar = probe(page, 'range').locator('.pika-lendar').nth(which)
  if (!(await calendar.isVisible())) await input(page, 'range').click()
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

test.describe('in a browser on UTC', () => {
  test.use({ timezoneId: 'UTC' })

  test.describe('Asia/Kuala_Lumpur', () => {
    const range = '2026-08-31T16:00:00Z to 2026-10-31T15:59:59Z'

    test('a stored range reads as the zone\'s days', async ({ page }) => {
      await open(page, { tz: KL, range })
      await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 31 Oct 2026')
    })

    test('a picked day is the whole day in the zone', async ({ page }) => {
      await open(page, { tz: KL })
      await pickRange(page, 0, 2026, 9, 1)
      await pickRange(page, 1, 2026, 10, 31)

      await expect(out(page, 'range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-31T15:59:59.999Z')
      await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 31 Oct 2026')
    })

    test('the calendar highlights the zone\'s days, and a picked day moves a stored range', async ({ page }) => {
      await open(page, { tz: KL, range })
      await input(page, 'range').click()

      const start = probe(page, 'range').locator('.pika-lendar').nth(0)
      await expect(start.locator('.pika-select-month')).toHaveValue('8')
      await expect(start.locator('td.is-selected button')).toHaveText('1')

      await pickRange(page, 1, 2026, 10, 15)
      await expect(out(page, 'range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-15T15:59:59.999Z')
    })

    test('a preset is today and its bounds in the zone', async ({ page }) => {
      // 2026-10-31 20:00 UTC is already 1 Nov 04:00 in Kuala Lumpur.
      await open(page, { tz: KL })
      await preset(page, 'Today')
      await expect(out(page, 'range')).toHaveText('2026-10-31T16:00:00.000Z to 2026-11-01T15:59:59.999Z')
      await expect(input(page, 'range')).toHaveValue('01 Nov 2026 to 01 Nov 2026')

      await preset(page, 'This Month')
      await expect(out(page, 'range')).toHaveText('2026-10-31T16:00:00.000Z to 2026-11-30T15:59:59.999Z')
    })

    test('a typed range is the zone\'s days', async ({ page }) => {
      await open(page, { tz: KL })
      await type(page, 'range', '01/09/2026 - 31/10/2026')

      await expect(out(page, 'range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-31T15:59:59.999Z')
      await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 31 Oct 2026')
    })

    test('a single date reads, picks and types in the zone, and a pick keeps its time of day', async ({ page }) => {
      await open(page, { tz: KL, date: '2026-08-31T16:00:00.000Z' })
      await expect(input(page, 'date')).toHaveValue('01 Sep 2026')

      await pickDate(page, 'date', 2026, 9, 15)
      await expect(out(page, 'date')).toHaveText('2026-09-14T16:00:00.000Z')
      await expect(input(page, 'date')).toHaveValue('15 Sep 2026')

      await type(page, 'date', '02/09/2026')
      await expect(out(page, 'date')).toHaveText('2026-09-01T16:00:00.000Z')
    })

    test('a single date with no value starts its day at midnight in the zone', async ({ page }) => {
      await open(page, { tz: KL })
      await pickDate(page, 'date', 2026, 9, 1)
      await expect(out(page, 'date')).toHaveText('2026-08-31T16:00:00.000Z')
    })
  })

  test.describe('America/New_York, across the 1 Nov 2026 DST change', () => {
    test('a stored range reads as the zone\'s days', async ({ page }) => {
      await open(page, { tz: NY, range: '2026-10-25T04:00:00Z to 2026-11-06T04:59:59.999Z' })
      await expect(input(page, 'range')).toHaveValue('25 Oct 2026 to 05 Nov 2026')
    })

    test('a range over the change starts in EDT and ends in EST', async ({ page }) => {
      await open(page, { tz: NY })
      await pickRange(page, 0, 2026, 10, 25)
      await pickRange(page, 1, 2026, 11, 5)

      await expect(out(page, 'range')).toHaveText('2026-10-25T04:00:00.000Z to 2026-11-06T04:59:59.999Z')
      await expect(input(page, 'range')).toHaveValue('25 Oct 2026 to 05 Nov 2026')
    })

    test('the day the clocks go back is 25 hours long', async ({ page }) => {
      await open(page, { tz: NY })
      await pickRange(page, 0, 2026, 11, 1)
      await pickRange(page, 1, 2026, 11, 1)

      await expect(out(page, 'range')).toHaveText('2026-11-01T04:00:00.000Z to 2026-11-02T04:59:59.999Z')
    })

    test('a rolling preset crosses the change', async ({ page }) => {
      // 3 Nov 07:00 in New York: the last 7 days start on 28 Oct (EDT).
      await open(page, { tz: NY }, '2026-11-03T12:00:00Z')
      await preset(page, 'Last 7 Days')
      await expect(out(page, 'range')).toHaveText('2026-10-28T04:00:00.000Z to 2026-11-04T04:59:59.999Z')
      await expect(input(page, 'range')).toHaveValue('28 Oct 2026 to 03 Nov 2026')

      await preset(page, 'This Month')
      await expect(out(page, 'range')).toHaveText('2026-11-01T04:00:00.000Z to 2026-12-01T04:59:59.999Z')

      await preset(page, 'This Year')
      await expect(out(page, 'range')).toHaveText('2026-01-01T05:00:00.000Z to 2027-01-01T04:59:59.999Z')
    })

    test('a typed day on the change day is the whole 25 hours', async ({ page }) => {
      await open(page, { tz: NY })
      await type(page, 'range', '01/11/2026')
      await expect(out(page, 'range')).toHaveText('2026-11-01T04:00:00.000Z to 2026-11-02T04:59:59.999Z')
    })

    test('a date and time keeps the wall clock in the zone', async ({ page }) => {
      await open(page, { tz: NY, datetime: '2026-10-31T13:30:00.000Z' })
      await expect(input(page, 'date-time')).toHaveValue('31 Oct 2026 09:30 AM')

      // 9:30 on Monday 2 Nov is EST: the offset changed, the wall clock did not.
      await pickDate(page, 'date-time', 2026, 11, 2)
      await expect(out(page, 'date-time')).toHaveText('2026-11-02T14:30:00.000Z')
      await expect(input(page, 'date-time')).toHaveValue('02 Nov 2026 09:30 AM')

      await type(page, 'date-time', '03/11/2026 05:15 pm')
      await expect(out(page, 'date-time')).toHaveText('2026-11-03T22:15:00.000Z')

      // The time picker edits the zone's wall clock too.
      await input(page, 'date-time').click()
      const hour = probe(page, 'date-time').getByLabel('Hour')
      await hour.click()
      await page.keyboard.type('08')
      await page.keyboard.press('Tab')
      await expect(out(page, 'date-time')).toHaveText('2026-11-04T01:15:00.000Z')
      await expect(input(page, 'date-time')).toHaveValue('03 Nov 2026 08:15 PM')
    })
  })

  test.describe('no timezone: the browser\'s days, as before', () => {
    test('a stored range reads in the browser\'s zone, a pick and a typed range are its days', async ({ page }) => {
      await open(page, { range: '2026-08-31T16:00:00Z to 2026-10-31T15:59:59Z' })
      await expect(input(page, 'range')).toHaveValue('31 Aug 2026 to 31 Oct 2026')

      await pickRange(page, 0, 2026, 9, 1)
      await expect(out(page, 'range')).toHaveText('2026-09-01T00:00:00.000Z to 2026-10-31T15:59:59.000Z')
      await pickRange(page, 1, 2026, 10, 31)
      await expect(out(page, 'range')).toHaveText('2026-09-01T00:00:00.000Z to 2026-10-31T23:59:59.999Z')

      await type(page, 'range', '01/09/2026 - 31/10/2026')
      await expect(out(page, 'range')).toHaveText('2026-09-01T00:00:00.000Z to 2026-10-31T23:59:59.999Z')
    })

    test('a preset is the browser\'s today', async ({ page }) => {
      await open(page)
      await preset(page, 'Today')
      await expect(out(page, 'range')).toHaveText('2026-10-31T00:00:00.000Z to 2026-10-31T23:59:59.999Z')
    })

    test('a single date and time stay in the browser\'s zone', async ({ page }) => {
      await open(page, { datetime: '2026-10-31T13:30:00.000Z' })
      await expect(input(page, 'date-time')).toHaveValue('31 Oct 2026 01:30 PM')

      await pickDate(page, 'date-time', 2026, 11, 2)
      await expect(out(page, 'date-time')).toHaveText('2026-11-02T13:30:00.000Z')
    })
  })

  test('a zone the browser does not know falls back to the browser\'s, without throwing', async ({ page }) => {
    const errors = []
    page.on('pageerror', error => errors.push(error.message))

    await open(page, { tz: 'Not/AZone', range: '2026-08-31T16:00:00Z to 2026-10-31T15:59:59Z' })
    await expect(input(page, 'range')).toHaveValue('31 Aug 2026 to 31 Oct 2026')

    await preset(page, 'Today')
    await expect(out(page, 'range')).toHaveText('2026-10-31T00:00:00.000Z to 2026-10-31T23:59:59.999Z')
    expect(errors).toEqual([])
  })
})

// A browser that is AHEAD of the zone, so the browser's day and the zone's day differ the other way round.
test.describe('in a browser on Pacific/Auckland', () => {
  test.use({ timezoneId: 'Pacific/Auckland' })

  test('the zone still wins', async ({ page }) => {
    await open(page, { tz: NY, range: '2026-10-25T04:00:00Z to 2026-11-06T04:59:59.999Z' })
    await expect(input(page, 'range')).toHaveValue('25 Oct 2026 to 05 Nov 2026')

    await pickRange(page, 0, 2026, 10, 26)
    await expect(out(page, 'range')).toHaveText('2026-10-26T04:00:00.000Z to 2026-11-06T04:59:59.999Z')
  })
})

// helpers/zone.js in node.

test('importing the zone helper leaves the shared dayjs instance alone', () => {
  // No plugin: the calendar and atom.str rely on a bare dayjs (see helpers/typed-date.js).
  expect(dayjs().utc).toBeUndefined()
  expect(dayjs().tz).toBeUndefined()
  expect(dayjs('2024-10-01', 'YYYY-MM-DD HH:mm:ss').isValid()).toBe(true)
})

test('validZone accepts what the browser knows and nothing else', () => {
  expect(validZone('Asia/Kuala_Lumpur')).toBe('Asia/Kuala_Lumpur')
  expect(validZone(' UTC ')).toBe('UTC')
  for (const bad of [null, undefined, '', '  ', 'Not/AZone', 'kuala lumpur', 42, {}]) expect(validZone(bad), String(bad)).toBeNull()
  expect(zoneOf('Not/AZone').zone).toBeNull()
})

test('zoneInstant is right on both sides of a DST change', () => {
  const at = (zone, y, m, d, hour = 0, minute = 0) => zoneInstant({ year: y, month: m, day: d, hour, minute }, zone).toISOString()

  expect(at(KL, 2026, 9, 1)).toBe('2026-08-31T16:00:00.000Z')
  // New York, 2026: forward on 8 Mar (02:00 EST), back on 1 Nov (02:00 EDT).
  expect(at(NY, 2026, 3, 7, 12)).toBe('2026-03-07T17:00:00.000Z')
  expect(at(NY, 2026, 3, 9, 12)).toBe('2026-03-09T16:00:00.000Z')
  expect(at(NY, 2026, 10, 31, 12)).toBe('2026-10-31T16:00:00.000Z')
  expect(at(NY, 2026, 11, 2, 12)).toBe('2026-11-02T17:00:00.000Z')
  // Midnight of the day the clocks go back (EDT) and of the next (EST): the day between is 25 hours.
  expect(at(NY, 2026, 11, 1)).toBe('2026-11-01T04:00:00.000Z')
  expect(at(NY, 2026, 11, 2)).toBe('2026-11-02T05:00:00.000Z')
  // The repeated hour takes the first of its two.
  expect(at(NY, 2026, 11, 1, 1, 30)).toBe('2026-11-01T05:30:00.000Z')
  // Southern hemisphere, and a zone with a half-hour offset.
  expect(at('Australia/Sydney', 2026, 10, 4, 12)).toBe('2026-10-04T01:00:00.000Z')
  expect(at('Asia/Kolkata', 2026, 1, 1)).toBe('2025-12-31T18:30:00.000Z')
})

test('a wall clock survives the trip through the zone', () => {
  const zones = [KL, NY, 'Europe/London', 'Australia/Lord_Howe', 'Pacific/Auckland', 'Asia/Kolkata', 'America/Sao_Paulo', 'UTC']
  const from = Date.UTC(2026, 0, 1)

  // Plain comparisons collected into one list: a Playwright expect() per instant would take minutes.
  const wrong = []

  for (const zone of zones) {
    // Every 37 minutes across 2026, so each DST change is crossed and several instants fall near one.
    for (let ms = from; ms < from + 365 * 86400000; ms += 37 * 60000) {
      const p = zoneParts(ms, zone)
      const back = zoneInstant(p, zone)
      const again = zoneParts(back.getTime(), zone)

      // Its wall clock is the one asked for; the instant may differ only by the length of the DST shift (an hour, half an hour in Lord Howe) when a wall time happens twice.
      if (JSON.stringify(again) !== JSON.stringify(p) || Math.abs(back.getTime() - ms) > 3600000) {
        wrong.push(`${zone} ${new Date(ms).toISOString()} -> ${back.toISOString()}`)
      }
    }
  }

  expect(wrong).toEqual([])
})

test('a wall clock that does not exist lands next to the change', () => {
  const t = zoneInstant({ year: 2026, month: 3, day: 8, hour: 2, minute: 30 }, NY)
  expect(t.toISOString()).toBe('2026-03-08T06:30:00.000Z')
})

test('without a zone, the helper is the browser\'s plain dayjs', () => {
  const plain = zoneOf(null)
  const wall = dayjs(new Date(2026, 8, 1, 13, 45, 12, 345))

  expect(plain.read('2026-08-31T16:00:00.000Z').valueOf()).toBe(dayjs('2026-08-31T16:00:00.000Z').valueOf())
  expect(plain.write(wall)).toBe(wall.toISOString())
  expect(plain.start(wall)).toBe(wall.startOf('day').toISOString())
  expect(plain.end(wall)).toBe(wall.endOf('day').toISOString())
})

test('read and write are each other\'s inverse in a zone', () => {
  const zone = zoneOf(NY)

  for (const iso of ['2026-10-31T13:30:00.000Z', '2026-11-02T14:30:00.000Z', '2026-03-08T07:15:00.000Z', '2026-12-31T04:59:59.999Z']) {
    expect(zone.write(zone.read(iso)), iso).toBe(iso)
  }

  expect(zone.read('garbage').isValid()).toBe(false)
  expect(zone.start(zone.read('2026-11-01T20:00:00Z'))).toBe('2026-11-01T04:00:00.000Z')
  expect(zone.end(zone.read('2026-11-01T20:00:00Z'))).toBe('2026-11-02T04:59:59.999Z')
})
