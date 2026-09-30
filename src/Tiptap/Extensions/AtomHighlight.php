<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Jiannius\Atom\Tiptap\StyleValue;
use Tiptap\Marks\Highlight;

class AtomHighlight extends Highlight
{
    /**
     * The stock Highlight writes the stored colour into `data-color` and
     * `style` verbatim. Keep its parseHTML, but render only a validated colour
     * (StyleValue::color).
     */
    public function addAttributes()
    {
        $config = parent::addAttributes();

        if (isset($config['color'])) {
            $config['color']['renderHTML'] = function ($attributes) {
                $color = StyleValue::color($attributes->color ?? null);

                return $color ? ['data-color' => $color, 'style' => "background-color: {$color}"] : null;
            };
        }

        return $config;
    }
}
