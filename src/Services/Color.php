<?php
namespace Jiannius\Atom\Services;

use Illuminate\Support\Arr;

class Color
{
    /**
     * Get all colors
     */
    public static function all() : array
    {
        return json_decode(file_get_contents(__DIR__.'/../../json/colors.json'), true);
    }

    /**
     * Check a value is a strict hex colour: #rgb, #rgba, #rrggbb or #rrggbbaa.
     */
    public static function isHex(mixed $color): bool
    {
        return is_string($color) && preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/Di', trim($color)) === 1;
    }

    /**
     * The tone a colour name stands for, so a badge reads from the role tokens: `neutral`, `info`, `success`, `warning`,
     * `danger` or `accent`, or `inverse` for `black` (a solid fg fill). A tone name stands for itself; a name that is
     * unknown, empty or not a string is `neutral`. It never throws, so a status enum's colour can be anything.
     *
     * @return 'neutral'|'info'|'success'|'warning'|'danger'|'accent'|'inverse'
     */
    public static function tone(mixed $color): string
    {
        $name = is_string($color) ? strtolower(trim($color)) : '';

        return match ($name) {
            'info', 'blue', 'sky', 'cyan', 'indigo' => 'info',
            'success', 'green', 'emerald', 'teal', 'lime' => 'success',
            'warning', 'yellow', 'amber', 'orange' => 'warning',
            'danger', 'error', 'red', 'rose' => 'danger',
            'accent', 'primary', 'purple', 'violet', 'fuchsia', 'pink' => 'accent',
            'black', 'inverse' => 'inverse',
            default => 'neutral',
        };
    }

    /**
     * A hex colour as plain `#rrggbb`: short forms expanded and any alpha dropped (a translucent text colour would lose
     * the contrast a badge counts on); null for anything that is not a strict hex colour.
     */
    public static function opaque(mixed $color): ?string
    {
        if (! self::isHex($color)) {
            return null;
        }

        $hex = strtolower(substr(trim($color), 1));

        if (strlen($hex) <= 4) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.substr($hex, 0, 6);
    }

    /**
     * Shade a hex color; returns null for anything that is not one.
     */
    public static function shade($color, $percent, $alpha = 1)
    {
        if (! self::isHex($color)) return;

        $color = substr(trim($color), 1);

        if (strlen($color) <= 4) {
            $r = hexdec(substr($color, 0, 1) . substr($color, 0, 1));
            $g = hexdec(substr($color, 1, 1) . substr($color, 1, 1));
            $b = hexdec(substr($color, 2, 1) . substr($color, 2, 1));
        } else {
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));
        }

        $rgb = [$r, $g, $b];
        $rgb = Arr::map($rgb, fn ($x) => dechex($x));
        $rgb = Arr::map($rgb, fn ($x) => round(hexdec($x) + round(((255 - hexdec($x)) * $percent) / 100)));

        if ($alpha < 1) $rgb = 'rgba(' . implode(',', $rgb) . ', ' . $alpha . ')';
        else $rgb = '#'.implode('',Arr::map($rgb, fn ($x) => str_pad(dechex($x), 2, '0', STR_PAD_LEFT)));

        return $rgb;
    }
}
