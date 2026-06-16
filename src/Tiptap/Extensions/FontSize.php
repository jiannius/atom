<?php

namespace Jiannius\Atom\Tiptap\Extensions;

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
     * class; any other value renders an inline font-size (e.g. "1.25rem").
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

                        return isset($sizes[$value])
                            ? ['data-font-size' => $value, 'class' => $sizes[$value]]
                            : ['data-font-size' => $value, 'style' => "font-size: {$value}"];
                    },
                ],
            ],
        ]];
    }
}
