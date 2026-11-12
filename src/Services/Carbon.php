<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Auth;

class Carbon extends \Carbon\CarbonImmutable
{
    /**
     * Convert carbon instance to string
     */
    public function __toString()
    {
        return $this->toIso8601ZuluString();
    }

    /**
     * Convert carbon instance to date range string
     */
    public function toDateRangeString($to = null)
    {
        $fr = $this->toDateTimeString();
        $to = ($to ?? now())->toDateTimeString();

        return $fr . ' to ' . $to;
    }

    /**
     * Convert carbon instance to local timezone
     */
    public function local()
    {
        $tz = self::getLocalTimezone();

        return $tz ? $this->timezone($tz) : $this;
    }

    /**
     * Prettify carbon string
     */
    public function pretty ($option = null)
    {
        $option = $option ?? 'date';

        if ($option === 'date') $format = 'd M Y';
        elseif ($option === 'datetime') $format = 'd M Y g:iA';
        elseif ($option === 'datetime-24') $format = 'd M Y H:i:s';
        elseif ($option === 'time') $format = 'g:i A';
        elseif ($option === 'time-24') $format = 'H:i:s';
        else $format = $option;

        return $this->local()->format($format);
    }

    /**
     * Get recent carbon string
     */
    public function recent ($days = 1)
    {
        if ($this->isToday()) return $this->pretty('time');
        if ($this->gte(now()->subDays($days))) return $this->local()->fromNow();

        return $this->pretty('datetime');
    }

    /**
     * Get local timezone: the signed-in user's, else `config('atom.timezone')` (atom's config/atom.php reads `env('TIMEZONE')` when the config is
     * loaded, so it survives `config:cache`; the host's own config/atom.php wins), else `env('TIMEZONE')` (null at runtime once the config is cached,
     * unless TIMEZONE is a real process variable, as when the config is cached at image build time), else Asia/Kuala_Lumpur.
     */
    public static function getLocalTimezone()
    {
        return optional(Auth::user())->timezone ?? config('atom.timezone') ?? env('TIMEZONE') ?? 'Asia/Kuala_Lumpur';
    }

    /**
     * Get range of carbon instance
     */
    public static function getRange($range)
    {
        $range = $range ?? '1970-01-01 00:00:00 to '.now()->toDateTimeString();

        // A bare `Y-m-d` end (what a date-only picker writes) is a whole day in the viewer's timezone: the range runs from the start of the
        // first day to the last microsecond of the last. Anything else is an instant, read as it always was.
        $from = head(explode('to', $range));
        $from = DateRange::isDate(trim($from)) ? DateRange::dayStart(trim($from)) : new self($from ?: '1970-01-01 00:00:00');

        $to = last(explode('to', $range));
        $to = DateRange::isDate(trim($to)) ? DateRange::nextDayStart(trim($to))->subMicrosecond() : ($to ? new self($to) : now());

        $diff = [
            'd' => round($from->diffInDays($to)),
            'm' => round($from->diffInMonths($to)),
            'y' => round($from->diffInYears($to)),
        ];

        $past = data_get($diff, 'd') > 0 ? [
            'from' => $from->copy()->subDays(data_get($diff, 'd')),
            'to' => $to->copy()->subDays(data_get($diff, 'd')),
        ] : null;

        $tz = now()->timezone(self::getLocalTimezone())->format('P');

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'diff' => $diff,
            'past' => $past,
            'tz' => $tz,
        ];
    }
}
