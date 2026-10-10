<?php

namespace Jiannius\Atom\Services;

use Carbon\CarbonImmutable;
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

    public const TIME_FORMATS = ['g:i A', 'g:iA', 'h:i A', 'h:iA', 'H:i', 'G:i'];

    public const DEFAULT_TIME_FORMAT = 'g:i A';

    /** @var list<string> */
    public array $views;

    public string $view;

    public string $weekStart;

    /** @var list<string> */
    public array $weekend;

    public ?int $max;

    public string $timeFormat;

    public bool $selectable;

    public bool $draggable;

    public bool $resizable;

    public int $slotDuration;

    public string $scrollTime;

    public bool $nowIndicator;

    /**
     * Build a calendar from the component's props; anything unrecognised falls back to its default.
     *
     * @param  array{views?: mixed, view?: mixed, week_start?: mixed, weekend?: mixed, max?: mixed, time_format?: mixed, selectable?: mixed, draggable?: mixed, resizable?: mixed, slot_duration?: mixed, scroll_time?: mixed, now_indicator?: mixed}  $options
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

        $format = $options['time_format'] ?? null;
        $this->timeFormat = is_string($format) && in_array($format, self::TIME_FORMATS, true) ? $format : self::DEFAULT_TIME_FORMAT;

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
     * @return array{weekdays: list<array{key: string, short: string, long: string}>, months: list<string>, am: string, pm: string, ui: array<string, string>}
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
            'selectable' => $this->selectable,
            'draggable' => $this->draggable,
            'resizable' => $this->resizable,
            'slotDuration' => $this->slotDuration,
            'scrollTime' => $this->scrollTime,
            'nowIndicator' => $this->nowIndicator,
        ];
    }
}
