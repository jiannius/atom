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
     * Get local timezone
     */
    public static function getLocalTimezone()
    {
        return optional(Auth::user())->timezone ?? env('TIMEZONE') ?? 'Asia/Kuala_Lumpur';
    }

    /**
     * Get range of carbon instance
     */
    public static function getRange($range)
    {
        $range = $range ?? '1970-01-01 00:00:00 to '.now()->toDateTimeString();

        $from = head(explode('to', $range));
        $from = new self($from ?: '1970-01-01 00:00:00');

        $to = last(explode('to', $range));
        $to = $to ? new self($to) : now();

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
