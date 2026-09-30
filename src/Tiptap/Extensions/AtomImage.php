<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Jiannius\Atom\Tiptap\StyleValue;
use Tiptap\Nodes\Image;

class AtomImage extends Image
{
    /**
     * An image source is a relative path, an http(s) URL or a raster data: URI.
     * Any other scheme (javascript:, vbscript:, data:image/svg+xml, ...) is
     * dropped, so a stored value can never put a script URL into the page.
     */
    public static function isSafeSource(mixed $src): bool
    {
        if (! is_string($src)) {
            return false;
        }

        $bare = preg_replace('/[\x00-\x20\x{00A0}\x{1680}\x{180E}\x{2000}-\x{2029}\x{205F}\x{3000}]/u', '', $src);

        if ($bare === null || $bare === '') {
            return false;
        }

        if (! preg_match('/^[a-z][a-z0-9+.\-]*:/i', $bare)) {
            return true;
        }

        return (bool) preg_match('/^(?:https?:|data:image\/(?:png|jpe?g|gif|webp|avif);base64,)/i', $bare);
    }

    /**
     * Add atom's float / align / width attributes (data-* + inline style),
     * mirroring the JS ImageExtended. Float and width are validated (StyleValue);
     * an invalid value renders neither the data attribute nor the style. HTML::mergeAttributes merges the style
     * fragments, so per-attribute renderHTML is safe.
     */
    public function addAttributes()
    {
        return array_merge(parent::addAttributes(), [
            'float' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-float') ?: null,
                'renderHTML' => function ($attributes) {
                    $float = StyleValue::float($attributes->float ?? null);

                    return $float ? ['data-float' => $float, 'style' => "float: {$float}"] : null;
                },
            ],
            'align' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-align') ?: null,
                'renderHTML' => function ($attributes) {
                    if (empty($attributes->align)) {
                        return null;
                    }

                    $style = match ($attributes->align) {
                        'left' => 'margin-right: auto',
                        'center' => 'margin-left: auto; margin-right: auto',
                        'right' => 'margin-left: auto',
                        default => null,
                    };

                    return $style ? ['data-align' => $attributes->align, 'style' => $style] : null;
                },
            ],
            'width' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-width') ?: null,
                'renderHTML' => function ($attributes) {
                    $width = StyleValue::width($attributes->width ?? null);

                    return $width ? ['data-width' => $width, 'style' => "width: {$width}"] : null;
                },
            ],
        ]);
    }

    /**
     * Render the image, or nothing at all when its source is not a safe one.
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        if (! static::isSafeSource($node->attrs->src ?? null)) {
            return null;
        }

        return parent::renderHTML($node, $HTMLAttributes);
    }
}
