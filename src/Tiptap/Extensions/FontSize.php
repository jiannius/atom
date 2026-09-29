<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Jiannius\Atom\Tiptap\StyleValue;
use Tiptap\Core\Extension;

class FontSize extends Extension
{
    public static $name = 'fontSize';

    public function addOptions()
    {
        return ['types' => ['textStyle']];
    }

    /**
     * Mirror the JS FontSize: preset keys (xs..xl) render a Tailwind text-*
     * class; a numeric px/em/rem value in range renders an inline font-size
     * (e.g. "1.25rem"); anything else is dropped.
     */
    public function addGlobalAttributes()
    {
        return [[
            'types' => $this->options['types'],
            'attributes' => [
                'fontSize' => [
                    'default' => null,
                    'parseHTML' => fn ($node) => $node->getAttribute('data-font-size') ?: null,
                    'renderHTML' => function ($attributes) {
                        if (empty($attributes->fontSize)) {
                            return null;
                        }

                        $sizes = ['xs' => 'text-xs', 'sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'];
                        $value = $attributes->fontSize;

                        if (is_string($value) && isset($sizes[$value])) {
                            return ['data-font-size' => $value, 'class' => $sizes[$value]];
                        }

                        $size = StyleValue::fontSize($value);

                        return $size ? ['data-font-size' => $size, 'style' => "font-size: {$size}"] : null;
                    },
                ],
            ],
        ]];
    }
}
