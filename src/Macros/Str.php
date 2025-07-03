<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Arr;

class Str
{
    public function namespace()
    {
        return function ($string) {
            $string = str($string)->replace('.', '\\')->replace('/', '\\');

            return collect(explode('\\', $string))
                ->map(fn ($value) => str()->studly($value))
                ->join('\\');
        };
    }

    public function dotpath()
    {
        return function ($string) {
            return str($string)
                ->replace('/', '.')
                ->replace('\\', '.')
                ->replace(' ', '.')
                ->toString()
                ;
        };
    }

    public function interval()
    {
        return function($string) {
            $count = trim(head(explode(' ', $string)));
            $interval = trim(last(explode(' ', $string)));
            $interval = Arr::pick([
                'day' => in_array($interval, ['day', 'days']),
                'week' => in_array($interval, ['week', 'weeks']),
                'month' => in_array($interval, ['month', 'months']),
                'year' => in_array($interval, ['year', 'years']),
            ]);

            if ($count == 1 && $interval === 'day') return t('daily');
            if ($count == 1 && $interval === 'month') return t('monthly');
            if ($count == 3 && $interval === 'month') return t('quarterly');
            if ($count == 6 && $interval === 'month') return t('half-yearly');
            if ($count == 1 && $interval === 'week') return t('weekly');
            if ($count == 1 && $interval === 'year') return t('yearly');

            if ($interval === 'day') return t('day-count', $count);
            if ($interval === 'week') return t('week-count', $count);
            if ($interval === 'month') return t('month-count', $count);
            if ($interval === 'year') return t('year-count', $count);
        };
    }
}