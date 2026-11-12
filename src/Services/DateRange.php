<?php

namespace Jiannius\Atom\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A date range as a date picker writes it, read on the server.
 *
 * With `time` off a picker writes the days picked (`2026-09-01 to 2026-09-30`), which carry no zone: a day is a whole calendar day in the
 * viewer's timezone (`Carbon::getLocalTimezone()`). Against a DATE column that is a plain date comparison, but against a DATETIME or
 * TIMESTAMP column, stored in the app's timezone, it is the half-open interval from the viewer's midnight of the first day up to the
 * viewer's midnight after the last. Anything that is not a bare `Y-m-d` (an ISO instant from a bookmarked link or a saved filter, a
 * `Y-m-d H:i:s` string) is an instant and is read as it always was.
 */
class DateRange
{
    /**
     * Split a range given as `from to to`, or as a `[from, to]` list, into its two ends, as given (a list may hold dates). A missing end is an
     * empty string.
     *
     * @return array{0: mixed, 1: mixed}
     */
    public static function split(mixed $range): array
    {
        $range = is_string($range)
            ? collect(explode('to', $range))->map(fn ($val) => str($val)->replace('to', ''))->map(fn ($val) => trim($val))->toArray()
            : $range;

        return [data_get($range, '0', ''), data_get($range, '1', '')];
    }

    /**
     * Whether a value is a bare, real `Y-m-d` date (not an instant, and not a date that does not exist).
     */
    public static function isDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }

        $parts = CalendarItem::parseWallClock($value);

        // the day after must be one too (9999-12-31 has none), or the exclusive end cannot be worked out
        return $parts !== null && $parts['time'] === null && $parts['offset'] === null
            && CalendarItem::parseWallClock(self::nextDate($value)) !== null;
    }

    /**
     * The first instant of a `Y-m-d` day in the viewer's timezone, as an instant in the app's timezone.
     */
    public static function dayStart(string $date): Carbon
    {
        return Calendar::fromLocal($date);
    }

    /**
     * The first instant of the day after a `Y-m-d` day in the viewer's timezone: the exclusive end of that day. The next day is worked out
     * on the date, never as 24 hours later, so a day with a daylight saving change is its real length.
     */
    public static function nextDayStart(string $date): Carbon
    {
        return Calendar::fromLocal(self::nextDate($date));
    }

    /**
     * The calendar date after a `Y-m-d` date, as `Y-m-d`.
     */
    protected static function nextDate(string $date): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    }
}
