<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Jiannius\Atom\Tiptap\StyleValue;
use Tiptap\Extensions\Color;

class AtomColor extends Color
{
    /**
     * The stock Color writes the stored value into `style` verbatim. Keep its
     * parseHTML, but render only a validated colour (StyleValue::color).
     */
    public function addGlobalAttributes()
    {
        $global = parent::addGlobalAttributes();

        $global[0]['attributes']['color']['renderHTML'] = function ($attributes) {
            $color = StyleValue::color($attributes?->color ?? null);

            return $color ? ['style' => "color: {$color}"] : null;
        };

        return $global;
    }
}
