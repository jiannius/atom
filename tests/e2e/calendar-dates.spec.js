import { test, expect } from '@playwright/test'
import { addDays, addMonths, diffDays, fromLocalDate, isoWeekday, monthOf, parseDate, timeOf, toLocalDate } from '../../resources/js/alpinejs/calendar/dates.js'

// Wall-clock date arithmetic must not depend on the machine's timezone: the server sends local
// strings and the browser never converts them. Each case runs under zones that put a DST change
// and a date-line shift in the way (Los Angeles springs forward on 2026-03-08 and falls back on
// 2026-11-01; Kiritimati is UTC+14; Apia skipped a whole day in 2011).

const zones = ['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Apia', 'Asia/Kuala_Lumpur']

for (const zone of zones) {
    test.describe(zone, () => {
        let previous

        test.beforeEach(() => {
            previous = process.env.TZ
            process.env.TZ = zone
        })

        test.afterEach(() => {
            if (previous === undefined) delete process.env.TZ
            else process.env.TZ = previous
        })

        test('adds days across a daylight saving change by the calendar, not by 24 hours', () => {
            expect(addDays('2026-03-07', 1)).toBe('2026-03-08')
            expect(addDays('2026-03-08', 1)).toBe('2026-03-09')
            expect(addDays('2026-10-31', 1)).toBe('2026-11-01')
            expect(addDays('2026-11-01', 1)).toBe('2026-11-02')
            expect(addDays('2026-03-01', 42)).toBe('2026-04-12')
            expect(addDays('2026-01-01', -1)).toBe('2025-12-31')
            expect(addDays('2024-02-28', 2)).toBe('2024-03-01')
            expect(addDays('2026-12-31', 1)).toBe('2027-01-01')
        })

        test('counts days between dates across the same changes', () => {
            expect(diffDays('2026-03-01', '2026-04-12')).toBe(42)
            expect(diffDays('2026-10-25', '2026-11-08')).toBe(14)
            expect(diffDays('2026-11-08', '2026-10-25')).toBe(-14)
            expect(diffDays('2026-10-09', '2026-10-09')).toBe(0)
        })

        test('finds the ISO weekday and month of a date', () => {
            expect(isoWeekday('2026-10-05')).toBe(1)
            expect(isoWeekday('2026-10-11')).toBe(7)
            expect(isoWeekday('2026-03-08')).toBe(7)
            expect(monthOf('2026-10-09')).toBe('2026-10')
        })

        test('moves by months and clamps to the end of a shorter one', () => {
            expect(addMonths('2026-10-09', 1)).toBe('2026-11-09')
            expect(addMonths('2026-10-09', -1)).toBe('2026-09-09')
            expect(addMonths('2026-01-31', 1)).toBe('2026-02-28')
            expect(addMonths('2024-01-31', 1)).toBe('2024-02-29')
            expect(addMonths('2026-12-15', 1)).toBe('2027-01-15')
            expect(addMonths('2026-01-15', -1)).toBe('2025-12-15')
            expect(addMonths('2026-03-31', -1)).toBe('2026-02-28')
            expect(addMonths('2026-10-09', 12)).toBe('2027-10-09')
        })

        test('reads the picker instant as the local day the user clicked, and writes it back', () => {
            // a picked local midnight, whatever the zone, is that day
            for (const day of ['2026-03-08', '2026-11-01', '2026-10-09', '2026-01-01']) {
                expect(fromLocalDate(toLocalDate(day))).toBe(day)
            }

            expect(fromLocalDate('not a date')).toBeNull()
        })
    })
}

test('refuses what is not a real date', () => {
    expect(parseDate('2026-02-30')).toBeNull()
    expect(parseDate('2026-13-01')).toBeNull()
    expect(parseDate('x')).toBeNull()
    expect(parseDate('2026-10-09T10:00')).toMatchObject({ y: 2026, m: 10, d: 9 })
})

test('reads the time of a moment, and none from a date', () => {
    expect(timeOf('2026-10-09T09:30')).toBe('09:30')
    expect(timeOf('2026-10-09')).toBeNull()
})
