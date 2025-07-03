<?php

use Illuminate\Support\Js;
use Jiannius\Atom\Services\Carbon;

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
