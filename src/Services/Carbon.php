<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Auth;

class Carbon extends \Carbon\CarbonImmutable
{
    public function local()
    {
        $tz = self::getLocalTimezone();

        return $tz ? $this->timezone($tz) : $this;
    }

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

    public function recent ($days = 1)
    {
        if ($this->isToday()) return $this->pretty('time');
        if ($this->gte(now()->subDays($days))) return $this->local()->fromNow();

        return $this->pretty('datetime');
    }

    public static function getLocalTimezone()
    {
        return optional(Auth::user())->timezone ?? env('TIMEZONE') ?? 'Asia/Kuala_Lumpur';
    }

    public static function getRange($range)
    {
        $range = $range ?? '1970-01-01 00:00:00 to '.now()->toDateTimeString();

        $from = head(explode('to', $range));
        $from = new self($from ?: '1970-01-01 00:00:00');

        $to = last(explode('to', $range));
        $to = $to ? new self($to) : now();

        $diff = [
            'd' => $from->diffInDays($to),
            'm' => $from->diffInMonths($to),
            'y' => $from->diffInYears($to),
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
