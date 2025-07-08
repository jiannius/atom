<?php

use Illuminate\Support\Js;
use Illuminate\Support\Number;
use Jiannius\Atom\Services\Carbon;

/**
 * Short hand for Laravel Number helper
 */
if (!function_exists('num')) {
    function num($value)
    {
        return new class ($value) {
            public function __construct(public $value) {}

            public function __call($method, $args)
            {
                return Number::{$method}($this->value, ...$args);
            }

            public function currency($in = null, $rounding = false, $bracket = false, $short = false) : string
            {
                if (!is_numeric($this->value)) return $this->value ?? '';
        
                $value = (float) $this->value;
        
                if ($short) {
                    $amount = Number::abbreviate($value);
                    $currency = $in ? "$in $amount" : $amount;
                }
                else {
                    $amount = $rounding ? (round((float) $value * 2, 1)/2) : $value;
                    $currency = $in ? ($in.' '.Number::format($amount, 2)) : Number::format($amount, 2);
                }
        
                return ($bracket && $value < 0) ? '('.str($currency)->replaceFirst('-', '').')' : $currency;
            }
        };
    }
}

/**
 * Check if a value is an enum instance
 */
if (!function_exists('is_enum')) {
    function is_enum($value)
    {
        return $value instanceof UnitEnum || $value instanceof BackedEnum;
    }
}

/**
 * Translate a string with optional count and parameters
 */
if (!function_exists('t')) {
    function t($str, $count = 1, $params = [])
    {
        if (empty($str)) return '';

        if (is_numeric($count)) return trans_choice($str, $count, $params);
        if (is_array($count)) return __($str, $count);

        return __($str, $params);
    }
}

/**
 * Convert a PHP value to JavaScript
 */
if (!function_exists('js')) {
    function js($value) {
        return Js::from($value);
    }
}

/**
 * Create a carbon instance
 */
if (!function_exists('carbon')) {
    function carbon(...$args)
    {
        return new Carbon(...$args);
    }
}
