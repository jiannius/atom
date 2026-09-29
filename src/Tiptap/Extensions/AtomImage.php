<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Jiannius\Atom\Tiptap\StyleValue;
use Tiptap\Nodes\Image;

class AtomImage extends Image
{
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
}
