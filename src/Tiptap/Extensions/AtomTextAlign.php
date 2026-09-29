<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Extensions\TextAlign;

class AtomTextAlign extends TextAlign
{
    /**
     * The stock TextAlign interpolates the stored value into `style` verbatim.
     * Keep its parseHTML and default, but render only a strict member of the
     * alignment list (a non-string never renders, and never warns).
     */
    public function addGlobalAttributes()
    {
        $global = parent::addGlobalAttributes();

        $global[0]['attributes']['textAlign']['renderHTML'] = function ($attributes) {
            $align = $attributes->textAlign ?? null;

            if ($align === $this->options['defaultAlignment']) {
                return null;
            }

            return in_array($align, ['left', 'center', 'right', 'justify'], true)
                ? ['style' => "text-align: {$align}"]
                : null;
        };

        return $global;
    }
}
