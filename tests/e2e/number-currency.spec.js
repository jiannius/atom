import { test, expect } from '@playwright/test'
import '../../resources/js/prototypes/number.js'

// Number.prototype.currency() run in node. `maxPrecision` shows at least 2 and at
// most N decimals (N clamped to 2-100). Intl rounds half away from zero, which
// is PHP's half-up, the default of num()->currency().

test('maxPrecision shows at least 2 and at most N decimals', () => {
  expect((0.065).currency(null, false, 6)).toBe('0.065')
  expect((10).currency(null, false, 6)).toBe('10.00')
  expect((10.5).currency(null, false, 6)).toBe('10.50')
  expect((1234.5678).currency(null, false, 6)).toBe('1,234.5678')
  expect((0.1234567).currency(null, false, 6)).toBe('0.123457')
  expect((-0.065).currency('MYR', false, 6)).toBe('MYR -0.065')
})

test('rounds a tie away from zero, as the PHP default does', () => {
  expect((0.125).currency(null, false, 2)).toBe('0.13')
  expect((10.185).currency(null, false, 2)).toBe('10.19')
  expect((-0.125).currency(null, false, 2)).toBe('-0.13')
})

test('a numeric string works like the number', () => {
  expect((0.065).currency(null, false, '6')).toBe('0.065')
  expect((0.1234567).currency(null, false, ' 6 ')).toBe('0.123457')
})

test('clamps to 2 at the bottom and 100 at the top', () => {
  expect((0.065).currency(null, false, 1)).toBe('0.07')
  expect((0.065).currency(null, false, 0)).toBe('0.07')
  expect((0.065).currency(null, false, -5)).toBe('0.07')
  expect((0.065).currency(null, false, -Infinity)).toBe('0.07')

  // 100 is Intl's own limit: a larger value throws a RangeError unless it is clamped
  const decimals = value => value.split('.')[1].length
  for (const value of [101, 500, Infinity, '500']) {
    expect(() => (1).currency(null, false, value), String(value)).not.toThrow()
    expect(decimals((1e-100).currency(null, false, value)), String(value)).toBe(100)
  }
  expect(decimals((1e-100).currency(null, false, 100))).toBe(100)
  expect(decimals((1e-99).currency(null, false, 99))).toBe(99)
})

test('NaN, null, undefined, blank and non-numeric strings mean unset: the default', () => {
  const unset = (0.065).currency()

  expect(unset).toBe('0.065')
  for (const value of [NaN, null, undefined, '', '  ', 'abc']) {
    expect((0.065).currency(null, false, value), String(value)).toBe(unset)
  }
  // the default allows up to 3 decimals, so a 4th is cut
  expect((0.1234).currency()).toBe('0.123')
})

test('keeps the symbol and the 0.05 rounding step', () => {
  expect((0.065).currency('MYR', false, 6)).toBe('MYR 0.065')
  expect((10.12).currency('MYR', true, 6)).toBe('MYR 10.10')
})
