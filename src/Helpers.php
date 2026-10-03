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

            public function filesize($precision = 2) : string
            {
                return Number::filesize($this->value * 1024, $precision);
            }

            public function currency($in = null, $rounding = false, $bracket = false, $abbreviate = false, ?int $maxPrecision = null, string $roundingMode = 'half-up') : string
            {
                $roundingMode = strtolower($roundingMode);

                if (!in_array($roundingMode, ['half-up', 'half-even', 'half-down'], true)) {
                    throw new InvalidArgumentException("Unknown rounding mode [$roundingMode]; use 'half-up', 'half-even' or 'half-down'.");
                }

                if (!is_numeric($this->value)) return $this->value ?? '';
        
                $value = (float) $this->value;
        
                if ($abbreviate) {
                    $amount = Number::abbreviate($value);
                    $currency = $in ? "$in $amount" : $amount;
                }
                else {
                    $amount = $rounding ? (round((float) $value * 2, 1)/2) : $value;
                    $formatted = $this->formatDecimals($amount, $maxPrecision, $roundingMode);
                    $currency = $in ? ($in.' '.$formatted) : $formatted;
                }
        
                return ($bracket && $value < 0) ? '('.str($currency)->replaceFirst('-', '').')' : $currency;
            }

            /**
             * Format with exactly 2 decimals, or 2 to $maxPrecision (trailing zeros past the 2nd trimmed), using the given rounding mode ('half-up', 'half-even' or 'half-down')
             */
            private function formatDecimals(float $amount, ?int $maxPrecision, string $roundingMode) : string
            {
                if (!extension_loaded('intl')) {
                    throw new RuntimeException('The "intl" PHP extension is required to use the [currency] method.');
                }

                $icuMode = match ($roundingMode) {
                    'half-even' => NumberFormatter::ROUND_HALFEVEN,
                    'half-down' => NumberFormatter::ROUND_HALFDOWN,
                    default => NumberFormatter::ROUND_HALFUP,
                };

                $formatter = new NumberFormatter(Number::defaultLocale(), NumberFormatter::DECIMAL);
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, max(2, $maxPrecision ?? 2));
                $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, $icuMode);

                return $formatter->format($amount);
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
 * Check if a class is using a trait
 */
if (!function_exists('is_using_trait')) {
    function is_using_trait($class, $trait)
    {
        return in_array($trait, class_uses_recursive($class));
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

/**
 * Return a URL only if it is safe to put in an href or navigate to, else null.
 *
 * Allowed: http, https, mailto, tel, sms, and any scheme-less URL (relative,
 * root-relative, protocol-relative, fragment-only, query-only). The check runs
 * on what a browser would see: HTML entities are decoded until stable (numeric
 * ones with or without the semicolon, and the legacy names a browser also reads
 * without one, such as `&amp`), tab and newline are dropped from anywhere,
 * leading control characters and spaces are stripped, and the scheme is
 * lowercased. The first path segment is treated as a scheme when it holds a
 * colon, so a relative path such as `foo:bar` must be written `./foo:bar`.
 * Anything that is not a string, a Stringable or a number (a bool, an array) is
 * null. The pass limit is a fail-closed bound, not a rule the tests pin down.
 *
 * This makes a URL scheme-safe only. `//evil.com` is allowed, so a redirect
 * must also check the host. Keep in step with resources/js/helpers/safe-url.js;
 * tests/Fixtures/safe-url-corpus.json holds both to the same answers.
 */
if (!function_exists('safe_url')) {
    function safe_url(mixed $url) : ?string
    {
        static $legacyNames = [
            'AElig', 'AMP', 'Aacute', 'Acirc', 'Agrave', 'Aring', 'Atilde', 'Auml', 'COPY', 'Ccedil', 'ETH', 'Eacute',
            'Ecirc', 'Egrave', 'Euml', 'GT', 'Iacute', 'Icirc', 'Igrave', 'Iuml', 'LT', 'Ntilde', 'Oacute', 'Ocirc',
            'Ograve', 'Oslash', 'Otilde', 'Ouml', 'QUOT', 'REG', 'THORN', 'Uacute', 'Ucirc', 'Ugrave', 'Uuml', 'Yacute',
            'aacute', 'acirc', 'acute', 'aelig', 'agrave', 'amp', 'aring', 'atilde', 'auml', 'brvbar', 'ccedil', 'cedil',
            'cent', 'copy', 'curren', 'deg', 'divide', 'eacute', 'ecirc', 'egrave', 'eth', 'euml', 'frac12', 'frac14',
            'frac34', 'gt', 'iacute', 'icirc', 'iexcl', 'igrave', 'iquest', 'iuml', 'laquo', 'lt', 'macr', 'micro',
            'middot', 'nbsp', 'not', 'ntilde', 'oacute', 'ocirc', 'ograve', 'ordf', 'ordm', 'oslash', 'otilde', 'ouml',
            'para', 'plusmn', 'pound', 'quot', 'raquo', 'reg', 'sect', 'shy', 'sup1', 'sup2', 'sup3', 'szlig', 'thorn',
            'times', 'uacute', 'ucirc', 'ugrave', 'uml', 'uuml', 'yacute', 'yen', 'yuml',
        ];

        if ($url instanceof Stringable || is_int($url) || is_float($url)) $url = (string) $url;
        if (!is_string($url) || $url === '') return null;

        $normalised = $url;

        for ($pass = 0; ; $pass++) {
            if ($pass >= 8) return null; // fail closed on input that keeps decoding

            $decoded = preg_replace_callback('/&#(?:x([0-9a-f]+)|([0-9]+));?/i', function ($m) {
                $code = $m[1] !== '' ? hexdec($m[1]) : (int) $m[2];

                return $code > 0 && $code <= 0x10FFFF ? mb_chr($code, 'UTF-8') : "\u{FFFD}";
            }, $normalised) ?? $normalised;

            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            // the legacy names a browser reads without a semicolon (`&amp#106;` is `&#106;`)
            $decoded = preg_replace_callback('/&('.implode('|', $legacyNames).')(?!;)/', function ($m) {
                return html_entity_decode('&'.$m[1].';', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            }, $decoded) ?? $decoded;

            $decoded = str_replace(["\t", "\n", "\r"], '', $decoded);

            if ($decoded === $normalised) break;

            $normalised = $decoded;
        }

        $normalised = ltrim($normalised, "\x00..\x20");
        $segment = substr($normalised, 0, strcspn($normalised, '/?#'));
        $colon = strpos($segment, ':');

        if ($colon === false) return $url;

        return in_array(strtolower(substr($segment, 0, $colon)), ['http', 'https', 'mailto', 'tel', 'sms'], true) ? $url : null;
    }
}
