<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Arr;

class Str
{
    /**
     * Convert dot annotation to namespace
     */
    public function namespace()
    {
        return function ($string) {
            $string = str($string)->replace('.', '\\')->replace('/', '\\');

            return collect(explode('\\', $string))
                ->map(fn ($value) => str()->studly($value))
                ->join('\\');
        };
    }

    /**
     * Convert namespace to dot annotation
     */
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

    /**
     * Convert interval to human readable string
     */
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

            if ($count == 1 && $interval === 'day') return t('Daily');
            if ($count == 1 && $interval === 'month') return t('Monthly');
            if ($count == 3 && $interval === 'month') return t('Quarterly');
            if ($count == 6 && $interval === 'month') return t('Half yearly');
            if ($count == 1 && $interval === 'week') return t('Weekly');
            if ($count == 1 && $interval === 'year') return t('Yearly');

            if ($interval === 'day') return t(':count day', $count);
            if ($interval === 'week') return t(':count week', $count);
            if ($interval === 'month') return t(':count month', $count);
            if ($interval === 'year') return t(':count year', $count);
        };
    }

    /**
     * Get initials from string
     */
    public function initials()
    {
        return function ($string, $len = 2) {
            return str($string)
                ->explode(' ')
                ->take($len)
                ->map(fn ($word) => str($word)->substr(0, 1))
                ->implode('');
        };
    }
}