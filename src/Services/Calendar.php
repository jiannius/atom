<?php

namespace Jiannius\Atom\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * The data side of <atom:calendar>: the visible window, the viewer's "today"
 * and "now", translated labels, and the one payload the browser lays out.
 *
 * Everything here works in the viewer's timezone (Carbon::getLocalTimezone()),
 * never config('app.timezone'). The payload carries local wall-clock strings
 * only, so the browser never converts an instant.
 */
class Calendar
{
    public const VIEWS = ['month', 'week', 'day'];

    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public const WINDOW_DAYS = 42;

    public const MIN_YEAR = 1000;

    public const MAX_YEAR = 9000;

    public const SLOT_DURATIONS = [5, 10, 15, 20, 30, 60];

    /**
     * The PHP date() tokens a display format may use, one string of single-character tokens per kind.
     * Any other ASCII letter is refused, and so is a backslash: there is no escape, so a literal letter cannot be written.
     * The browser formatter mirrors exactly this set, nothing else, and `S` (the English-only ordinal suffix) is not in it.
     */
    public const FORMAT_TOKENS = [
        'date' => 'djDlmnMFyY',
        'time' => 'gGhHiaA',
    ];

    public const FORMAT_MAX_LENGTH = 32;

    public const DEFAULT_TIME_FORMAT = 'g:i A';

    public const DEFAULT_DATE_FORMAT = 'j M Y';

    /** @var list<string> */
    public array $views;

    public string $view;

    public string $weekStart;

    /** @var list<string> */
    public array $weekend;

    public ?int $max;

    public string $timeFormat;

    public string $dateFormat;

    public bool $selectable;

    public bool $draggable;

    public bool $resizable;

    public int $slotDuration;

    public string $scrollTime;

    public bool $nowIndicator;

    /**
     * Build a calendar from the component's props; anything unrecognised falls back to its default.
     *
     * @param  array{views?: mixed, view?: mixed, week_start?: mixed, weekend?: mixed, max?: mixed, time_format?: mixed, date_format?: mixed, selectable?: mixed, draggable?: mixed, resizable?: mixed, slot_duration?: mixed, scroll_time?: mixed, now_indicator?: mixed}  $options
     */
    public function __construct(array $options = [])
    {
        $views = collect(is_array($options['views'] ?? null) ? $options['views'] : [])
            ->map(fn ($view) => is_string($view) ? strtolower(trim($view)) : null)
            ->filter(fn ($view) => in_array($view, self::VIEWS, true))
            ->unique()
            ->values()
            ->all();

        $this->views = $views ?: ['month'];

        $view = $options['view'] ?? null;
        $this->view = is_string($view) && in_array(strtolower(trim($view)), $this->views, true) ? strtolower(trim($view)) : $this->views[0];

        $weekStart = $options['week_start'] ?? null;
        $this->weekStart = is_string($weekStart) && in_array(strtolower(trim($weekStart)), self::WEEKDAYS, true) ? strtolower(trim($weekStart)) : 'monday';

        $this->weekend = array_key_exists('weekend', $options) && is_array($options['weekend'])
            ? collect($options['weekend'])
                ->map(fn ($day) => is_string($day) ? strtolower(trim($day)) : null)
                ->filter(fn ($day) => in_array($day, self::WEEKDAYS, true))
                ->unique()
                ->values()
                ->all()
            : ['saturday', 'sunday'];

        $max = array_key_exists('max', $options) ? $options['max'] : 3;
        // no limit is written null, false, 0, '0', 'false' or 'none' (a prop can only say it with a string or a number); any other number below 1 or text is the default
        $unlimited = $max === null || $max === false || $max === 0
            || (is_string($max) && in_array(strtolower(trim($max)), ['0', 'false', 'none'], true));

        $this->max = $unlimited ? null : (is_numeric($max) && (int) $max >= 1 ? (int) $max : 3);

        $this->timeFormat = self::resolveFormat($options['time_format'] ?? null, 'time', self::DEFAULT_TIME_FORMAT);
        $this->dateFormat = self::resolveFormat($options['date_format'] ?? null, 'date', self::DEFAULT_DATE_FORMAT);

        $this->selectable = filter_var($options['selectable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->draggable = filter_var($options['draggable'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->resizable = filter_var($options['resizable'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $slot = $options['slot_duration'] ?? 30;
        $this->slotDuration = is_numeric($slot) && in_array((int) $slot, self::SLOT_DURATIONS, true) ? (int) $slot : 30;

        $scroll = $options['scroll_time'] ?? null;
        $this->scrollTime = is_string($scroll) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $scroll) ? $scroll : '06:00';

        $this->nowIndicator = filter_var($options['now_indicator'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Build a calendar from the component's props.
     *
     * @param  array<string, mixed>  $options
     */
    public static function make(array $options = []): static
    {
        return new static($options);
    }

    /**
     * Whether a value is a display format built only from FORMAT_TOKENS and literals, and uses at least one token.
     *
     * Literals are digits, spaces, punctuation and non-ASCII characters (`Y年n月j日`). Refused: any other ASCII letter,
     * a backslash, the HTML-significant characters
     * < > " ' & and the backtick, control, format and line-separator characters, invalid UTF-8, a non-string, and
     * a string over FORMAT_MAX_LENGTH characters.
     */
    public static function isFormat(mixed $format): bool
    {
        if (! is_string($format) || $format === '' || ! preg_match('//u', $format)) {
            return false;
        }

        if (mb_strlen($format, 'UTF-8') > self::FORMAT_MAX_LENGTH || preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}<>"\'&`\\\\]/u', $format)) {
            return false;
        }

        // ASCII only: ctype_alpha() follows the process locale and would call a byte of 年 a letter
        preg_match_all('/[A-Za-z]/', $format, $letters);

        $tokens = self::FORMAT_TOKENS['date'].self::FORMAT_TOKENS['time'];

        foreach ($letters[0] as $letter) {
            if (! str_contains($tokens, $letter)) {
                return false;
            }
        }

        return $letters[0] !== [];
    }

    /**
     * The format when it is valid and holds at least one token of the kind (`date` or `time`), else the default.
     */
    public static function resolveFormat(mixed $format, string $kind, string $default): string
    {
        return self::isFormat($format) && strpbrk($format, self::FORMAT_TOKENS[$kind]) !== false ? $format : $default;
    }

    /**
     * Format a date in the viewer's locale with a valid format; `D l M F a A` are translated, the rest is numeric.
     * `a` and `A` follow the locale's own period of the day (zh: 凌晨, 早上, 上午, 中午, 下午, 晚上), which is what labels()['meridiem'] carries.
     * Every name labels() carries is read from this same translatedFormat(), so the two print the same.
     * The date is printed as given: convert it to the viewer's timezone first. Throws on a format isFormat() refuses.
     */
    public static function format(CarbonInterface $date, string $format): string
    {
        if (! self::isFormat($format)) {
            throw new InvalidArgumentException('Not a calendar display format: '.$format);
        }

        return $date->copy()->locale(app()->getLocale())->translatedFormat($format);
    }

    /**
     * The viewer's timezone: the one source local(), pretty() and the calendar share.
     *
     * A user whose `timezone` is empty or not a zone PHP knows would make every date in the calendar throw, so it falls back to
     * the app timezone, reported once (the value is not in the report).
     */
    public static function timezone(): string
    {
        $zone = Carbon::getLocalTimezone();

        if (is_string($zone) && $zone !== '') {
            try {
                new DateTimeZone($zone);

                return $zone;
            } catch (Exception) {
                // reported below
            }
        }

        CalendarItem::reportKeyOnce(md5('timezone|'.(is_scalar($zone) ? $zone : gettype($zone))), 'The viewer timezone is empty or not a timezone PHP knows; the calendar uses the app timezone.');

        return self::appTimezone();
    }

    /**
     * The timezone instants are stored and queried in.
     */
    public static function appTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Convert a local wall-clock string (`Y-m-d` or `Y-m-d\TH:i[:s]`) to an app-timezone instant; throws on anything else.
     */
    public static function fromLocal(string $value): Carbon
    {
        $parts = CalendarItem::parseWallClock($value);

        if (! $parts || $parts['offset'] !== null) {
            throw new InvalidArgumentException('Not a local wall-clock value: '.$value);
        }

        return Carbon::parse($parts['date'].' '.($parts['time'] ?? '00:00:00'), self::timezone())
            ->setTimezone(self::appTimezone());
    }

    /**
     * The current moment, in the viewer's timezone.
     */
    public static function now(): Carbon
    {
        return now()->setTimezone(self::timezone());
    }

    /**
     * The viewer's local date today, as `Y-m-d`.
     */
    public function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    /**
     * The viewer's local date and time now, as `Y-m-d\TH:i:s`.
     *
     * Not part of payload(): the browser lays the month out from the payload and reads it again whenever it changes,
     * so a clock inside it would redo the layout on every render. The component prints this on an attribute of its own.
     */
    public function clock(): string
    {
        return self::now()->format('Y-m-d\TH:i:s');
    }

    /**
     * Resolve a client- or host-supplied anchor (`Y-m-d` or `Y-m`, years 1000 to 9000, or a date object) to a local date, else today.
     *
     * A date object is an instant: its date is the one it falls on in the viewer's timezone. A date-only value is
     * better given as `Y-m-d`, which is taken as it is.
     */
    public function anchor(mixed $value = null): string
    {
        if ($value instanceof DateTimeInterface) {
            $value = CarbonImmutable::instance($value)->setTimezone(self::timezone())->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/D', trim($value), $match)) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            $day = isset($match[3]) ? (int) $match[3] : 1;

            if ($year >= self::MIN_YEAR && $year <= self::MAX_YEAR && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return $this->today();
    }

    /**
     * The 6-week month grid containing the anchor; `from` and `to` are app-timezone instants, `to` inclusive (23:59:59.999999).
     *
     * @return array{anchor: string, start: string, end: string, days: list<array{date: string, weekday: string, weekend: bool, outside: bool}>, from: Carbon, to: Carbon}
     */
    public function window(mixed $anchor = null): array
    {
        $anchor = $this->anchor($anchor);

        // Plain date arithmetic in UTC: a local midnight can be skipped or repeated by DST,
        // so day-stepping happens on the date, and only the two edges become instants.
        $first = CarbonImmutable::parse($anchor, 'UTC')->startOfMonth();
        $weekStartIso = array_search($this->weekStart, self::WEEKDAYS, true) + 1;
        $offset = ($first->dayOfWeekIso - $weekStartIso + 7) % 7;
        $start = $first->subDays($offset);

        $days = [];

        for ($i = 0; $i < self::WINDOW_DAYS; $i++) {
            $day = $start->addDays($i);
            $weekday = strtolower($day->englishDayOfWeek);

            $days[] = [
                'date' => $day->format('Y-m-d'),
                'weekday' => $weekday,
                'weekend' => in_array($weekday, $this->weekend, true),
                'outside' => $day->format('Y-m') !== $first->format('Y-m'),
            ];
        }

        $end = $days[self::WINDOW_DAYS - 1]['date'];

        return [
            'anchor' => $anchor,
            'start' => $days[0]['date'],
            'end' => $end,
            'days' => $days,
            'from' => Carbon::parse($days[0]['date'].' 00:00:00', self::timezone())->setTimezone(self::appTimezone()),
            'to' => Carbon::parse($end.' 00:00:00', self::timezone())->endOfDay()->setTimezone(self::appTimezone()),
        ];
    }

    /**
     * Translated names and strings the browser prints, so no date or string is formatted in JS.
     *
     * Every name is what format() prints for its token, taken from translatedFormat() itself rather than from Carbon's name
     * lookups, which disagree with it in some locales (es, nl, lv, hr, lt): `months` and `months_short` are `F` and `M`,
     * `weekdays` carry `D` and `l`, and `meridiem` is the list of times of the day at which `a` and `A` change, as
     * `[startMinute, lower, upper]` from midnight, since a locale can name the period by the minute (zh: 中午 from 11:30 to
     * 12:29, 下午 from 12:30). A locale whose period also depends on the date is not covered: the list is read from one Monday.
     * `month_forms` holds the month names that change with the day of the month in the format (ru, pl, cs, uk, hr, lt: genitive
     * in `j F`), as `day_before` (a day token earlier in the format) and `day_after` lists, and only where they differ from the
     * standalone ones. One more known gap: ka prints a weekday differently beside other tokens.
     * `week_title`, `month_title` and `day_title` are how the title of each view is written in the locale, see weekTitle().
     *
     * @return array{weekdays: list<array{key: string, short: string, long: string}>, months: list<string>, months_short: list<string>, month_forms: array<string, array<string, list<string>>>, week_title: array<string, list<string>>, month_title: string, day_title: string, meridiem: list<array{0: int, 1: string, 2: string}>, am: string, pm: string, ui: array<string, string|array<string, string>>}
     */
    public function labels(): array
    {
        $locale = app()->getLocale();
        $monday = CarbonImmutable::parse('2026-10-05', 'UTC')->locale($locale);

        $weekdays = collect(self::WEEKDAYS)->map(fn ($key, $i) => [
            'key' => $key,
            'short' => $monday->addDays($i)->translatedFormat('D'),
            'long' => $monday->addDays($i)->translatedFormat('l'),
        ]);

        $offset = array_search($this->weekStart, self::WEEKDAYS, true);

        $month = fn (int $number, string $token) => CarbonImmutable::create(2026, $number, 1, 0, 0, 0, 'UTC')->locale($locale)->translatedFormat($token);

        $months = collect(range(1, 12))->map(fn ($number) => $month($number, 'F'))->all();
        $monthsShort = collect(range(1, 12))->map(fn ($number) => $month($number, 'M'))->all();

        return [
            'weekdays' => $weekdays->slice($offset)->merge($weekdays->slice(0, $offset))->values()->all(),
            'months' => $months,
            'months_short' => $monthsShort,
            'month_forms' => self::monthForms($locale, ['F' => $months, 'M' => $monthsShort]),
            'week_title' => self::weekTitle($locale),
            'month_title' => self::monthTitle($locale),
            'day_title' => self::dayTitle($locale),
            'meridiem' => self::meridiemTransitions($locale),
            'am' => (string) __('AM'),
            'pm' => (string) __('PM'),
            'ui' => [
                'today' => (string) __('Today'),
                'previous' => (string) __('Previous'),
                'next' => (string) __('Next'),
                'month' => (string) __('Month'),
                'week' => (string) __('Week'),
                'day' => (string) __('Day'),
                'all_day' => (string) __('All day'),
                'more' => (string) __('+:count more'),
                'overdue' => (string) __('Overdue'),
                'item' => (string) __(':count item'),
                'items' => (string) __(':count items'),
                'close' => (string) __('Close'),
                'choose_date' => (string) __('Choose date'),
                'view' => (string) __('View'),
                'open_day' => (string) __('Open :date in day view'),
                // the title of the placeholder that stands where a selection was while the host creates what it asked for
                'creating' => (string) __('Creating...'),
                // what is said while an item that was moved or resized waits for the host
                'saving' => (string) __('Saving...'),
                // an item that covers more than a day, for its name: ":start" and ":end" are each a day, and a time when the item has one
                'range' => (string) __(':start to :end'),
                // what a colour of the preset palette is called, for the name of an item: a host's own colour has no name here
                'colors' => [
                    'red' => (string) __('Red'),
                    'orange' => (string) __('Orange'),
                    'yellow' => (string) __('Yellow'),
                    'green' => (string) __('Green'),
                    'blue' => (string) __('Blue'),
                    'purple' => (string) __('Purple'),
                    'black' => (string) __('Black'),
                    'zinc' => (string) __('Grey'),
                    'gray' => (string) __('Grey'),
                ],
            ],
        ];
    }

    /**
     * How the week view's title is written in a locale: the first day's display format and the last day's, for a week inside
     * one month, over two months and over two years (`5 – 11 October 2026`, `28 Sep – 4 Oct 2026`, `28 Dec 2026 – 3 Jan 2027`).
     *
     * The browser prints each end with FORMAT_TOKENS and puts ` – ` between them, so the order of year, month and day is the
     * locale's and not the browser's: Chinese writes `2026年10月5日 – 11日`. A trailing `Y` in the last format is split off as the
     * year, which the title shows lighter. A locale without its own patterns reads like English, day before month before year.
     * Not tied to the `date-format` prop, which is for the dates inside the views.
     *
     * @return array{same_month: list<string>, cross_month: list<string>, cross_year: list<string>}
     */
    public static function weekTitle(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'zh')) {
            return [
                'same_month' => ['Y年n月j日', 'j日'],
                'cross_month' => ['Y年n月j日', 'n月j日'],
                'cross_year' => ['Y年n月j日', 'Y年n月j日'],
            ];
        }

        return [
            'same_month' => ['j', 'j F Y'],
            'cross_month' => ['j M', 'j M Y'],
            'cross_year' => ['j M Y', 'j M Y'],
        ];
    }

    /**
     * How the month view's title is written in a locale, as one display format: `October 2026`, Chinese `2026年10月`.
     *
     * Like weekTitle() it is a format the browser formatter prints, and a trailing `Y` is split off as the year, which the title
     * shows lighter; Chinese has no trailing year, so the whole title is one weight. A locale without a pattern of its own reads
     * like English.
     */
    public static function monthTitle(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'zh') ? 'Y年n月' : 'F Y';
    }

    /**
     * How the day view's title is written in a locale, as one display format: `9 October 2026`, Chinese `2026年10月9日`. See monthTitle().
     */
    public static function dayTitle(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'zh') ? 'Y年n月j日' : 'j F Y';
    }

    /**
     * The month names a locale prints differently when the format has a day of the month, from the same translatedFormat() as format().
     *
     * Carbon picks the form from the whole format: Russian, Polish, Czech, Croatian and others print the genitive in `j F`, and
     * Lithuanian in `F j` too. Only a form that differs from the standalone name is returned, under `F` or `M`.
     *
     * @param  array<string, list<string>>  $standalone  the standalone names, by token
     * @return array<string, array<string, list<string>>>
     */
    protected static function monthForms(string $locale, array $standalone): array
    {
        $forms = [];

        foreach ($standalone as $token => $names) {
            foreach (['day_before' => ['25 '.$token, '/^25\s+/u'], 'day_after' => [$token.' 25', '/\s+25$/u']] as $kind => [$format, $day]) {
                $printed = collect(range(1, 12))->map(function ($number) use ($locale, $format, $day) {
                    $text = CarbonImmutable::create(2026, $number, 25, 0, 0, 0, 'UTC')->locale($locale)->translatedFormat(str_replace('25', 'j', $format));

                    return preg_match($day, $text) ? preg_replace($day, '', $text) : null;
                })->all();

                if (! in_array(null, $printed, true) && $printed !== $names) {
                    $forms[$token][$kind] = $printed;
                }
            }
        }

        return $forms;
    }

    /**
     * The minutes of the day at which `a` and `A` change in a locale, each with what they print from there on.
     *
     * Read from every minute of one day, so a locale that names the period by the minute (zh) is right at 11:45 and 12:30.
     * The result depends on the locale alone and is kept for the process: labels() runs on every render.
     *
     * @return list<array{0: int, 1: string, 2: string}>
     */
    protected static function meridiemTransitions(string $locale): array
    {
        static $known = [];

        if (isset($known[$locale])) {
            return $known[$locale];
        }

        $midnight = CarbonImmutable::parse('2026-10-05', 'UTC')->locale($locale);
        $transitions = [];

        for ($minute = 0; $minute < 1440; $minute++) {
            $moment = $midnight->setTime(intdiv($minute, 60), $minute % 60);
            $lower = $moment->translatedFormat('a');
            $upper = $moment->translatedFormat('A');
            $last = $transitions[array_key_last($transitions)] ?? null;

            if (! $last || $last[1] !== $lower || $last[2] !== $upper) {
                $transitions[] = [$minute, $lower, $upper];
            }
        }

        return $known[$locale] = $transitions;
    }

    /**
     * Normalise items into payload items; one that cannot be placed, or repeats an earlier item's id, is skipped and reported once; one that is not an item throws.
     *
     * @param  iterable<mixed>  $items
     * @return list<array<string, mixed>>
     */
    public function items(iterable $items): array
    {
        $normalised = [];
        $seen = [];

        foreach ($items as $item) {
            $item = CalendarItem::normalise($item, [
                'draggable' => $this->draggable,
                'resizable' => $this->resizable,
            ]);

            if (! $item) {
                continue;
            }

            // the browser keys items by id, so 1 and '1' are the same item there
            $key = (string) $item['id'];

            if (isset($seen[$key])) {
                CalendarItem::reportDuplicate($item['id']);

                continue;
            }

            $seen[$key] = true;
            $normalised[] = $item;
        }

        return $normalised;
    }

    /**
     * The one payload the browser reads; `$items` is an iterable, or a closure called with the window's `$from` and `$to`.
     *
     * @param  iterable<mixed>|Closure(Carbon, Carbon): iterable<mixed>  $items
     * @return array<string, mixed>
     */
    public function payload(mixed $anchor = null, iterable|Closure $items = []): array
    {
        $window = $this->window($anchor);

        if ($items instanceof Closure) {
            $items = $items($window['from'], $window['to']) ?? [];
        }

        return [
            'anchor' => $window['anchor'],
            'today' => $this->today(),
            'timezone' => self::timezone(),
            'window' => [
                'start' => $window['start'],
                'end' => $window['end'],
                'days' => $window['days'],
            ],
            'labels' => $this->labels(),
            'config' => $this->config(),
            'items' => $this->items($items),
        ];
    }

    /**
     * The settings the browser needs.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'views' => $this->views,
            'view' => $this->view,
            'weekStart' => $this->weekStart,
            'weekend' => $this->weekend,
            'max' => $this->max,
            'timeFormat' => $this->timeFormat,
            'dateFormat' => $this->dateFormat,
            'selectable' => $this->selectable,
            'draggable' => $this->draggable,
            'resizable' => $this->resizable,
            'slotDuration' => $this->slotDuration,
            'scrollTime' => $this->scrollTime,
            'nowIndicator' => $this->nowIndicator,
        ];
    }
}
