<?php

namespace Jiannius\Atom\Tiptap;

/**
 * Allow-list validators for values the renderer writes into a style attribute.
 * Stored content is untrusted (a client can save any JSON), so every value
 * that reaches inline CSS goes through one of these. Each returns the
 * normalised value, or null when the value must be dropped.
 */
class StyleValue
{
    /** CSS named colours (plus transparent / currentcolor). */
    protected const NAMED_COLORS = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue',
        'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk',
        'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki',
        'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
        'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue',
        'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
        'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred',
        'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral',
        'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
        'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow', 'lime',
        'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
        'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue',
        'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
        'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip',
        'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue',
        'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue',
        'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise',
        'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen', 'transparent', 'currentcolor',
    ];

    /**
     * A colour: hex, rgb()/rgba(), hsl()/hsla() or a CSS named colour.
     */
    public static function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $num = '\d{1,3}(?:\.\d+)?';
        $alpha = '(?:\d*\.)?\d+%?';
        $hue = '-?\d+(?:\.\d+)?(?:deg|grad|rad|turn)?';

        $valid = preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)
            || preg_match("/^rgba?\(\s*{$num}%?\s*,\s*{$num}%?\s*,\s*{$num}%?\s*(?:,\s*{$alpha}\s*)?\)$/i", $value)
            || preg_match("/^rgba?\(\s*{$num}%?\s+{$num}%?\s+{$num}%?\s*(?:\/\s*{$alpha}\s*)?\)$/i", $value)
            || preg_match("/^hsla?\(\s*{$hue}\s*,\s*{$num}%\s*,\s*{$num}%\s*(?:,\s*{$alpha}\s*)?\)$/i", $value)
            || preg_match("/^hsla?\(\s*{$hue}\s+{$num}%\s+{$num}%\s*(?:\/\s*{$alpha}\s*)?\)$/i", $value)
            || in_array(strtolower($value), self::NAMED_COLORS, true);

        return $valid ? $value : null;
    }

    /**
     * A custom font size: a number in px (1-999) or em/rem (0.1-20).
     */
    public static function fontSize(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,3}(?:\.\d+)?)(px|em|rem)$/i', $size = trim($value), $m)) {
            return null;
        }

        [$min, $max] = strtolower($m[2]) === 'px' ? [1, 999] : [0.1, 20];

        return ((float) $m[1] >= $min && (float) $m[1] <= $max) ? $size : null;
    }

    /**
     * An image width: a number in px (1-5000) or % (1-100).
     */
    public static function width(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,4}(?:\.\d+)?)(px|%)$/i', $size = trim($value), $m)) {
            return null;
        }

        $max = $m[2] === '%' ? 100 : 5000;

        return ((float) $m[1] >= 1 && (float) $m[1] <= $max) ? $size : null;
    }

    /**
     * An image float: left, right or none.
     */
    public static function float(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['left', 'right', 'none'], true) ? $value : null;
    }
}
