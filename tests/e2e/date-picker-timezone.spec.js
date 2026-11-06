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
    const warnings = []
    page.on('pageerror', error => errors.push(error.message))
    page.on('console', message => message.type() === 'warning' && warnings.push(message.text()))

    await open(page, { tz: 'Not/AZone', range: '2026-08-31T16:00:00Z to 2026-10-31T15:59:59Z' })
    await expect(input(page, 'range')).toHaveValue('31 Aug 2026 to 31 Oct 2026')

    await preset(page, 'Today')
    await expect(out(page, 'range')).toHaveText('2026-10-31T00:00:00.000Z to 2026-10-31T23:59:59.999Z')
    expect(errors).toEqual([])

    // told once for the typo, though three pickers on the page carry it
    expect(warnings.filter(text => text.includes('Not/AZone'))).toHaveLength(1)
  })

  test('no prop, an empty one: no warning', async ({ page }) => {
    const warnings = []
    page.on('console', message => message.type() === 'warning' && warnings.push(message.text()))

    await open(page)
    await expect(input(page, 'range')).toBeVisible()
    expect(warnings.filter(text => text.includes('timezone'))).toEqual([])
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

  test('a date or a date-time with no offset is a time in the zone, not in the browser', async ({ page }) => {
    // read as the browser's, 2026-09-01 in Auckland is 31 Aug in Kuala Lumpur
    await open(page, { tz: KL, range: '2026-09-01 to 2026-10-31', date: '2026-09-01', datetime: '2026-09-01T09:30:00' })

    await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 31 Oct 2026')
    await expect(input(page, 'date')).toHaveValue('01 Sep 2026')
    await expect(input(page, 'date-time')).toHaveValue('01 Sep 2026 09:30 AM')

    // and the day picked on it is the zone's
    await pickRange(page, 1, 2026, 10, 15)
    await expect(out(page, 'range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-15T15:59:59.999Z')
  })

  test.describe('no timezone: the browser\'s days, as before', () => {
    test('a stored range, a pick, a typed range and a preset are the browser\'s days', async ({ page }) => {
      // NZST (+12) on 1 Sep, NZDT (+13) on 31 Oct
      await open(page, { range: '2026-08-31T16:00:00Z to 2026-10-31T15:59:59Z' })
      await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 01 Nov 2026')

      await pickRange(page, 0, 2026, 9, 1)
      await expect(out(page, 'range')).toHaveText('2026-08-31T12:00:00.000Z to 2026-10-31T15:59:59.000Z')
      await pickRange(page, 1, 2026, 10, 31)
      await expect(out(page, 'range')).toHaveText('2026-08-31T12:00:00.000Z to 2026-10-31T10:59:59.999Z')

      await type(page, 'range', '01/09/2026 - 31/10/2026')
      await expect(out(page, 'range')).toHaveText('2026-08-31T12:00:00.000Z to 2026-10-31T10:59:59.999Z')

      // 2026-10-31 20:00 UTC is 1 Nov 09:00 in Auckland
      await preset(page, 'Today')
      await expect(out(page, 'range')).toHaveText('2026-10-31T11:00:00.000Z to 2026-11-01T10:59:59.999Z')
    })

    test('a single date and time stay in the browser\'s zone', async ({ page }) => {
      await open(page, { datetime: '2026-10-31T13:30:00.000Z', date: '2026-10-31T11:00:00.000Z' })
      await expect(input(page, 'date-time')).toHaveValue('01 Nov 2026 02:30 AM')
      await expect(input(page, 'date')).toHaveValue('01 Nov 2026')

      await pickDate(page, 'date-time', 2026, 11, 3)
      await expect(out(page, 'date-time')).toHaveText('2026-11-02T13:30:00.000Z')
      await pickDate(page, 'date', 2026, 11, 3)
      await expect(out(page, 'date')).toHaveText('2026-11-02T11:00:00.000Z')
    })
  })
})

// The same pickers bound to Livewire (the fixture of date-picker-typed.spec.js, `?tz=` handing them the prop): what reaches the server is the zone's
// day, and a re-render of the component (the deferred form's save) leaves what the pickers show and hold as it was.
test.describe('through a Livewire re-render', () => {
  test.use({ timezoneId: 'UTC' })

  test('a typed range and date reach the server as the zone\'s days and survive a re-render', async ({ page }) => {
    await page.goto('/atom/e2e/date-picker-typed?tz=' + KL)
    await page.waitForFunction(() => window.Livewire && window.Alpine)

    const server = (name) => probe(page, name).locator('[data-server]')

    await type(page, 'range', '01/09/2026 - 31/10/2026')
    await expect(server('range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-31T15:59:59.999Z')

    await type(page, 'date', '02/09/2026')
    await expect(server('date')).toHaveText('2026-09-01T16:00:00.000Z')

    await type(page, 'deferred-date', '03/09/2026')
    await type(page, 'deferred-range', '04/09/2026 - 05/09/2026')
    await input(page, 'deferred-range').press('Escape') // closes the panel the typing left open
    await page.locator('[data-save]').click()
    await expect(page.locator('[data-saves]')).toHaveText('1')
    await expect(server('deferred-date')).toHaveText('2026-09-02T16:00:00.000Z')
    await expect(server('deferred-range')).toHaveText('2026-09-03T16:00:00.000Z to 2026-09-05T15:59:59.999Z')

    // the component has been rendered again: what is shown and held is as it was
    await expect(input(page, 'range')).toHaveValue('01 Sep 2026 to 31 Oct 2026')
    await expect(input(page, 'date')).toHaveValue('02 Sep 2026')
    await expect(input(page, 'deferred-date')).toHaveValue('03 Sep 2026')
    await expect(input(page, 'deferred-range')).toHaveValue('04 Sep 2026 to 05 Sep 2026')

    // and it still picks in the zone afterwards
    await pickRange(page, 1, 2026, 10, 15)
    await expect(server('range')).toHaveText('2026-08-31T16:00:00.000Z to 2026-10-15T15:59:59.999Z')
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
  // The repeated hour takes the first of its two, in every zone and in both hemispheres.
  expect(at(NY, 2026, 11, 1, 1, 30)).toBe('2026-11-01T05:30:00.000Z')
  expect(at('Europe/London', 2026, 10, 25, 1, 30)).toBe('2026-10-25T00:30:00.000Z')
  expect(at('Australia/Sydney', 2026, 4, 5, 2, 30)).toBe('2026-04-04T15:30:00.000Z')
  expect(at('Australia/Lord_Howe', 2026, 4, 5, 1, 45)).toBe('2026-04-04T14:45:00.000Z')
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
    let repeated = 0

    // Every 37 minutes across 2026, so each DST change is crossed and several instants fall near one.
    for (let ms = from; ms < from + 365 * 86400000; ms += 37 * 60000) {
      const p = zoneParts(ms, zone)
      const back = zoneInstant(p, zone)
      const again = zoneParts(back.getTime(), zone)
      const behind = ms - back.getTime()

      // Its wall clock is the one asked for, and it is the same instant, except for the second time round of a repeated hour, which is read as the
      // first: earlier by the length of the shift (an hour; half an hour in Lord Howe) and never later.
      if (JSON.stringify(again) !== JSON.stringify(p) || ![0, 1800000, 3600000].includes(behind)) wrong.push(`${zone} ${new Date(ms).toISOString()} -> ${back.toISOString()}`)
      if (behind) repeated++
    }

    // only the hour the clocks repeat, once a year (an hour is under two of the 37-minute steps)
    if (repeated > 2) wrong.push(`${zone}: ${repeated} instants read as an earlier one`)
  }

  expect(wrong).toEqual([])
})

test('a wall time the clocks skip is read forward, after the change', () => {
  const at = (zone, y, m, d, hour, minute = 0) => zoneInstant({ year: y, month: m, day: d, hour, minute }, zone).toISOString()

  expect(at(NY, 2026, 3, 8, 2, 30)).toBe('2026-03-08T07:30:00.000Z') // 03:30 EDT
  expect(at('Europe/London', 2026, 3, 29, 1, 30)).toBe('2026-03-29T01:30:00.000Z') // 02:30 BST
  expect(at('Australia/Sydney', 2026, 10, 4, 2, 30)).toBe('2026-10-03T16:30:00.000Z') // 03:30 AEDT
  expect(at('Australia/Lord_Howe', 2026, 10, 4, 2, 15)).toBe('2026-10-03T15:45:00.000Z') // 02:45, the half-hour shift
})

test('a day whose midnight the clocks skip starts at the first instant of that day, never the day before', () => {
  const zone = (name) => zoneOf(name)

  for (const [name, day, start] of [
    ['America/Santiago', '2026-09-06T12:00:00Z', '2026-09-06T04:00:00.000Z'], // 00:00 does not exist: the day starts at 01:00
    ['America/Havana', '2026-03-08T17:00:00Z', '2026-03-08T05:00:00.000Z'],
  ]) {
    const z = zone(name)
    const wall = z.read(day)

    expect(z.start(wall), name).toBe(start)
    // the first instant of that day on the clock, and an instant before it is the day before
    expect(zoneParts(Date.parse(start), name).day, name).toBe(wall.date())
    expect(zoneParts(Date.parse(start) - 1, name).day, name).not.toBe(wall.date())
  }
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

test('a value with no offset is a time in the zone; one with an offset is an instant', () => {
  const zone = zoneOf(KL)

  expect(zone.instant('2026-09-01')).toBe('2026-08-31T16:00:00.000Z')
  expect(zone.instant('2026-09-01T09:30:00')).toBe('2026-09-01T01:30:00.000Z')
  expect(zone.instant('2026-09-01 09:30:15.5')).toBe('2026-09-01T01:30:15.500Z')
  expect(zone.instant('2026-09-01T09:30:00Z')).toBe('2026-09-01T09:30:00.000Z')
  expect(zone.instant('2026-09-01T09:30:00+08:00')).toBe('2026-09-01T01:30:00.000Z')

  expect(zone.read('2026-09-01').format('YYYY-MM-DD HH:mm')).toBe('2026-09-01 00:00')
  expect(zone.read('2026-09-01T09:30:00').format('YYYY-MM-DD HH:mm')).toBe('2026-09-01 09:30')
  expect(zone.read('2026-09-01T09:30:00Z').format('YYYY-MM-DD HH:mm')).toBe('2026-09-01 17:30')

  // without a zone these are the browser's, as dayjs reads them
  expect(zoneOf(null).instant('2026-09-01T09:30:00')).toBe(dayjs('2026-09-01T09:30:00').toISOString())
})
