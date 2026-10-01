import { test, expect } from '@playwright/test'
import dayjs from 'dayjs'
import { parseTypedDate, splitTypedRange } from '../../resources/js/helpers/typed-date.js'

// The String prototype helpers (resources/js/prototypes/string.js) call
// `dayjs(value, 'YYYY-MM-DD HH:mm:ss')` and rely on dayjs core IGNORING the format.
// A parse plugin extended onto the shared instance would make a `...Z` string lose
// its zone and a date-only string invalid, so the parser must not install one.
test('importing the parser leaves the shared dayjs instance alone', () => {
  expect(dayjs('2024-10-01', 'YYYY-MM-DD HH:mm:ss').isValid()).toBe(true)
  expect(dayjs('2024-10-01T16:00:00.000000Z', 'YYYY-MM-DD HH:mm:ss').toISOString()).toBe('2024-10-01T16:00:00.000Z')
})

// The parser behind the date pickers' typed input (humblebear#338), run in node.
// The pickers' wiring to Livewire is covered against a real page in
// date-picker-typed.spec.js.

const ymd = (text, options) => parseTypedDate(text, options)?.date.format('YYYY-MM-DD HH:mm')

test('accepts the display format, day-first numerics and ISO', () => {
  for (const text of [
    '01 Oct 2024', '1 Oct 2024', '01 oct 2024', '1 OCT 2024', '1 October 2024', '  01   Oct 2024 ',
    '01/10/2024', '1/10/2024', '01-10-2024', '1.10.2024', '2024-10-01', '2024-10-1',
  ]) {
    expect(ymd(text), text).toBe('2024-10-01 00:00')
  }
})

test('reads numerics day first, never month first', () => {
  expect(ymd('05/06/2024')).toBe('2024-06-05 00:00')
  expect(ymd('12/31/2024')).toBeUndefined()
})

test('refuses partial, impossible and unrelated text', () => {
  for (const text of [
    '', '   ', '01', '01/10', '01/10/24', '31/02/2024', '29/02/2023', '32/01/2024', '00/01/2024',
    '01/13/2024', 'Oct 2024', 'next tuesday', '01 Octo 2024', '2024-13-01', '1e3', null, undefined,
  ]) {
    expect(parseTypedDate(text), String(text)).toBeNull()
  }

  expect(ymd('29/02/2024')).toBe('2024-02-29 00:00')
})

test('takes a time only when asked to', () => {
  expect(parseTypedDate('01/10/2024 09:30 PM')).toBeNull()

  for (const text of ['01/10/2024 09:30 PM', '01/10/2024 9:30 pm', '01 Oct 2024 09:30PM', '01/10/2024 21:30']) {
    expect(ymd(text, { time: true }), text).toBe('2024-10-01 21:30')
    expect(parseTypedDate(text, { time: true }).hasTime).toBe(true)
  }

  expect(parseTypedDate('01/10/2024', { time: true }).hasTime).toBe(false)
  expect(parseTypedDate('01/10/2024 25:00', { time: true })).toBeNull()
  expect(parseTypedDate('01/10/2024 13:00 PM', { time: true })).toBeNull()
})

test('splits a range on a spaced separator, not on an ISO dash', () => {
  expect(splitTypedRange('01/10/2024 - 31/12/2024')).toEqual(['01/10/2024', '31/12/2024'])
  expect(splitTypedRange('01 Oct 2024 to 31 Dec 2024')).toEqual(['01 Oct 2024', '31 Dec 2024'])
  expect(splitTypedRange('2024-10-01 – 2024-12-31')).toEqual(['2024-10-01', '2024-12-31'])
  expect(splitTypedRange('2024-10-01 ~ 2024-12-31')).toEqual(['2024-10-01', '2024-12-31'])
  expect(splitTypedRange('2024-10-01')).toEqual(['2024-10-01'])
  expect(splitTypedRange('01 Oct 2024 09:00 AM TO 02 Oct 2024 05:00 PM')).toEqual(['01 Oct 2024 09:00 AM', '02 Oct 2024 05:00 PM'])
})
