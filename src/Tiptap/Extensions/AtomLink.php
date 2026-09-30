<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Marks\Link;
use Tiptap\Utils\HTML;

class AtomLink extends Link
{
    /**
     * The stock Link merges every stored mark attribute into the anchor, so a
     * client could set `class` (host utility classes), replace `rel`, or use
     * any `target`. Render only a checked href, the configured target/rel, and
     * the editor's own new-tab switch: `_blank` (default) or `_self`, anything
     * else, including null, removes the target.
     */
    public function renderHTML($mark, $HTMLAttributes = [])
    {
        $href = $HTMLAttributes['href'] ?? '';
        $href = is_string($href) && $this->options['isAllowedUri']($href) ? $href : '';

        $attributes = HTML::mergeAttributes($this->options['HTMLAttributes'], ['href' => $href]);
        $stored = (array) ($mark->attrs ?? []);

        if (array_key_exists('target', $stored)) {
            if ($stored['target'] === '_self') {
                $attributes['target'] = '_self';
            } elseif ($stored['target'] !== '_blank') {
                unset($attributes['target']);
            }
        }

        return ['a', $attributes, 0];
    }
}
