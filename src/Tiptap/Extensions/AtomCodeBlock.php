<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Nodes\CodeBlock;
use Tiptap\Utils\HTML;

class AtomCodeBlock extends CodeBlock
{
    /**
     * The stock CodeBlock appends the stored language to a class verbatim, so a
     * value containing spaces adds arbitrary classes. Only a single token of
     * language-name characters (letters, digits, _ + # . -) is rendered.
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $language = $node->attrs->language ?? null;
        $valid = is_string($language) && preg_match('/^[\w+#.-]{1,40}\z/', $language);

        return [
            'pre',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes),
            [
                'code',
                ['class' => $valid ? $this->options['languageClassPrefix'].$language : null],
                0,
            ],
        ];
    }
}
