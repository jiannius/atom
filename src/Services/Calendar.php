<?php

namespace Jiannius\Atom\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
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
        $this->max = $max === null ? null : (is_numeric($max) && (int) $max >= 1 ? (int) $max : 3);

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
     */
    public static function timezone(): string
    {
        return Carbon::getLocalTimezone();
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
     */
    public function clock(): string
    {
        return self::now()->format('Y-m-d\TH:i:s');
    }

    /**
     * Resolve a client- or host-supplied anchor (`Y-m-d` or `Y-m`, years 1000 to 9000) to a local date, else today.
     */
    public function anchor(mixed $value = null): string
    {
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
     * `months` and `months_short` are the long and short names for `F` and `M`; `weekdays` carry `D` and `l`; `meridiem.lower` and
     * `meridiem.upper` are `a` and `A` for each hour 0 to 23, since some locales name more than two periods of the day.
     *
     * @return array{weekdays: list<array{key: string, short: string, long: string}>, months: list<string>, months_short: list<string>, meridiem: array{lower: list<string>, upper: list<string>}, am: string, pm: string, ui: array<string, string>}
     */
    public function labels(): array
    {
        $locale = app()->getLocale();
        $monday = CarbonImmutable::parse('2026-10-05', 'UTC')->locale($locale);

        $weekdays = collect(self::WEEKDAYS)->map(fn ($key, $i) => [
            'key' => $key,
            'short' => $monday->addDays($i)->shortDayName,
            'long' => $monday->addDays($i)->dayName,
        ]);

        $offset = array_search($this->weekStart, self::WEEKDAYS, true);

        return [
            'weekdays' => $weekdays->slice($offset)->merge($weekdays->slice(0, $offset))->values()->all(),
            'months' => collect(range(1, 12))
                ->map(fn ($month) => CarbonImmutable::create(2026, $month, 1, 0, 0, 0, 'UTC')->locale($locale)->monthName)
                ->all(),
            'months_short' => collect(range(1, 12))
                ->map(fn ($month) => CarbonImmutable::create(2026, $month, 1, 0, 0, 0, 'UTC')->locale($locale)->shortMonthName)
                ->all(),
            'meridiem' => [
                'lower' => collect(range(0, 23))->map(fn ($hour) => $monday->setTime($hour, 0)->translatedFormat('a'))->all(),
                'upper' => collect(range(0, 23))->map(fn ($hour) => $monday->setTime($hour, 0)->translatedFormat('A'))->all(),
            ],
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
            ],
        ];
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
            'now' => $this->clock(),
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
