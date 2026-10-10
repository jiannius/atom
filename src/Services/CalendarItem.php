<?php

namespace Jiannius\Atom\Services;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Stringable;

/**
 * Turns one host item (an array or Arrayable) into the safe, timezone-normalised
 * shape <atom:calendar> lays out.
 *
 * Payload item: id, title, start, end, allDay, color, icon, danger, href, draggable, resizable.
 * - start / end are local wall-clock strings in the viewer's timezone: `Y-m-d` for an all-day item,
 *   `Y-m-d\TH:i` for a timed one. A date end is inclusive; a timed end is exclusive. `end` is null
 *   when the item has no end, or an end that is not after its start.
 * - color is a preset name (or a name added in config('atom.calendar.colors')), a lowercase `#hex`, or null.
 * - icon is the name of an atom icon that exists, or null.
 * - href has passed safe_url(), or is null.
 *
 * A missing id, title or start throws: it is a programming error. A start that will not parse skips
 * the item (null), reported once; a bad colour, icon, href or end is dropped and the item stays.
 */
class CalendarItem
{
    public const COLORS = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'black', 'zinc', 'gray'];

    /** @var array<string, true> */
    protected static array $reported = [];

    /**
     * Normalise one item. Returns null when the item cannot be placed (its start will not parse).
     *
     * @param  array{draggable?: bool, resizable?: bool}  $defaults  what the component's props say
     * @return array<string, mixed>|null
     */
    public static function normalise(mixed $item, array $defaults = []): ?array
    {
        $data = self::toArray($item);

        $id = $data['id'] ?? null;

        if ((! is_int($id) && ! is_string($id)) || $id === '') {
            throw new InvalidArgumentException('A calendar item needs an id.');
        }

        $title = $data['title'] ?? null;

        if (! is_string($title) && ! is_numeric($title) && ! $title instanceof Stringable) {
            throw new InvalidArgumentException('Calendar item '.$id.' needs a title.');
        }

        if (($data['start'] ?? null) === null || $data['start'] === '') {
            throw new InvalidArgumentException('Calendar item '.$id.' needs a start.');
        }

        $start = self::moment($data['start']);

        if (! $start) {
            self::reportOnce($id, 'start', $data['start']);

            return null;
        }

        $end = array_key_exists('end', $data) && $data['end'] !== null && $data['end'] !== '' ? self::moment($data['end']) : null;

        if (($data['end'] ?? null) !== null && ($data['end'] ?? '') !== '' && ! $end) {
            self::reportOnce($id, 'end', $data['end']);
        }

        $allDay = array_key_exists('all_day', $data) && $data['all_day'] !== null
            ? filter_var($data['all_day'], FILTER_VALIDATE_BOOLEAN)
            : $start['bare'];

        [$from, $to] = $allDay ? self::allDaySpan($start, $end) : self::timedSpan($start, $end);

        return [
            'id' => $id,
            'title' => (string) $title,
            'start' => $from,
            'end' => $to,
            'allDay' => $allDay,
            'color' => self::color($data['color'] ?? null),
            'icon' => self::icon($data['icon'] ?? null),
            'danger' => filter_var($data['danger'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'href' => is_string($data['href'] ?? null) && $data['href'] !== '' ? safe_url($data['href']) : null,
            'draggable' => self::flag($data, 'draggable', $defaults['draggable'] ?? true),
            'resizable' => self::flag($data, 'resizable', $defaults['resizable'] ?? true),
        ];
    }

    /**
     * The colour names an item may use: the presets plus any name added in config('atom.calendar.colors').
     *
     * @return list<string>
     */
    public static function colors(): array
    {
        $added = collect(array_keys((array) config('atom.calendar.colors', [])))
            ->filter(fn ($name) => is_string($name) && preg_match('/^[a-z][a-z0-9-]*$/D', $name))
            ->all();

        return array_values(array_unique([...self::COLORS, ...$added]));
    }

    /**
     * Split a wall-clock string (`Y-m-d`, or date, `T` or space, `H:i[:s[.f]]`, and an optional `Z` / `+08:00`
     * offset after a time) into its parts. Null when the shape or any part is invalid.
     *
     * @return array{date: string, time: ?string, offset: ?string}|null
     */
    public static function parseWallClock(string $value): ?array
    {
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,9})?)?(Z|[+-]\d{2}(?::?\d{2})?)?)?$/D';

        if (! preg_match($pattern, trim($value), $m)) {
            return null;
        }

        if ((int) $m[1] < 1000 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        $time = null;

        if (isset($m[4]) && $m[4] !== '') {
            if ((int) $m[4] > 23 || (int) $m[5] > 59 || (int) ($m[6] ?? 0) > 59) {
                return null;
            }

            $time = sprintf('%02d:%02d:%02d', $m[4], $m[5], $m[6] ?? 0);
        }

        $offset = $m[7] ?? null;

        if ($offset && $offset !== 'Z' && ((int) substr($offset, 1, 2) > 23 || (int) substr(str_replace(':', '', $offset), 3, 2) > 59)) {
            return null;
        }

        return ['date' => "$m[1]-$m[2]-$m[3]", 'time' => $time, 'offset' => $offset === '' ? null : $offset];
    }

    /**
     * A host item as an array.
     *
     * @return array<string, mixed>
     */
    protected static function toArray(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if ($item instanceof Arrayable) {
            return $item->toArray();
        }

        throw new InvalidArgumentException('A calendar item must be an array or Arrayable.');
    }

    /**
     * Read a date or datetime into the viewer's local date and time. A bare `Y-m-d` is a calendar date and is
     * never converted; a Carbon / DateTime keeps its own zone, an offset string is exact, and a string with no
     * offset is read in the app timezone.
     *
     * @return array{bare: bool, date: string, time: ?string, instant: ?Carbon}|null
     */
    protected static function moment(mixed $value): ?array
    {
        if ($value instanceof DateTimeInterface) {
            $instant = Carbon::instance($value);
        } elseif (is_string($value) && ($parts = self::parseWallClock($value))) {
            if ($parts['time'] === null) {
                return ['bare' => true, 'date' => $parts['date'], 'time' => null, 'instant' => null];
            }

            $instant = Carbon::parse($parts['date'].' '.$parts['time'].($parts['offset'] ?? ''), $parts['offset'] ? null : Calendar::appTimezone());
        } else {
            return null;
        }

        $local = $instant->setTimezone(Calendar::timezone());

        return ['bare' => false, 'date' => $local->format('Y-m-d'), 'time' => $local->format('H:i:s'), 'instant' => $instant];
    }

    /**
     * Start and end dates of an all-day item. An instant end runs up to that instant minus one second.
     *
     * @return array{0: string, 1: ?string}
     */
    protected static function allDaySpan(array $start, ?array $end): array
    {
        $to = null;

        if ($end) {
            $to = $end['bare'] ? $end['date'] : $end['instant']->subSecond()->setTimezone(Calendar::timezone())->format('Y-m-d');
        }

        return [$start['date'], $to !== null && $to > $start['date'] ? $to : null];
    }

    /**
     * Start and end wall-clock minutes of a timed item. A bare date is local midnight.
     *
     * @return array{0: string, 1: ?string}
     */
    protected static function timedSpan(array $start, ?array $end): array
    {
        $from = $start['date'].'T'.substr($start['time'] ?? '00:00', 0, 5);
        $to = $end ? $end['date'].'T'.substr($end['time'] ?? '00:00', 0, 5) : null;

        return [$from, $to !== null && $to > $from ? $to : null];
    }

    /**
     * A colour the calendar knows: a preset or configured name, or a strict hex. Anything else is dropped.
     */
    protected static function color(mixed $color): ?string
    {
        if (is_object($color) && method_exists($color, 'color')) {
            $color = $color->color();
        }

        if (! is_string($color)) {
            return null;
        }

        $color = trim($color);

        if (Color::isHex($color)) {
            return strtolower($color);
        }

        return in_array(strtolower($color), self::colors(), true) ? strtolower($color) : null;
    }

    /**
     * An atom icon name that exists, else null. The name is checked as a slug before it touches the filesystem.
     */
    protected static function icon(mixed $icon): ?string
    {
        if (! is_string($icon) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $icon) || $icon === 'index') {
            return null;
        }

        return is_file(__DIR__.'/../../components/icon/'.$icon.'.blade.php') ? $icon : null;
    }

    /**
     * A per-item boolean override, else the component's default.
     */
    protected static function flag(array $data, string $key, bool $default): bool
    {
        return array_key_exists($key, $data) && $data[$key] !== null
            ? filter_var($data[$key], FILTER_VALIDATE_BOOLEAN)
            : $default;
    }

    /**
     * Report a value the calendar could not read, once per item field and value. The message names the id and
     * field, never the value.
     */
    protected static function reportOnce(int|string $id, string $field, mixed $value): void
    {
        $key = md5($id.'|'.$field.'|'.(is_scalar($value) ? $value : gettype($value)));

        if (isset(self::$reported[$key]) || count(self::$reported) >= 100) {
            return;
        }

        self::$reported[$key] = true;

        report(new InvalidArgumentException('Calendar item '.$id.' has an unreadable '.$field.'.'));
    }
}
