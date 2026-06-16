<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Nodes\Image;

class AtomImage extends Image
{
    /**
     * Add atom's float / align / width attributes (data-* + inline style),
     * mirroring the JS ImageExtended. HTML::mergeAttributes merges the style
     * fragments, so per-attribute renderHTML is safe.
     */
    public function addAttributes()
    {
        return array_merge(parent::addAttributes(), [
            'float' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-float') ?: null,
                'renderHTML' => fn ($attributes) => empty($attributes->float)
                    ? null
                    : ['data-float' => $attributes->float, 'style' => "float: {$attributes->float}"],
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
                'renderHTML' => fn ($attributes) => empty($attributes->width)
                    ? null
                    : ['data-width' => $attributes->width, 'style' => "width: {$attributes->width}"],
            ],
        ]);
    }
}
