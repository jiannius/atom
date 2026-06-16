<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Nodes\Mention;
use Tiptap\Utils\HTML;

class AtomMention extends Mention
{
    /**
     * Add the `label` attribute (the JS mention stores id + label) on top of
     * the built-in `id`.
     */
    public function addAttributes()
    {
        return array_merge(parent::addAttributes(), [
            'label' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-label') ?: null,
                'renderHTML' => fn ($attributes) => empty($attributes->label)
                    ? null
                    : ['data-label' => $attributes->label],
            ],
        ]);
    }

    /**
     * Render atom's mention markup: <span class="mention" data-type="mention" ...>@Label</span>.
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        return [
            'span',
            HTML::mergeAttributes(['class' => 'mention', 'data-type' => 'mention'], $this->options['HTMLAttributes'], $HTMLAttributes),
            0,
        ];
    }

    /**
     * Provide the text content for the mention node (@Label).
     * Called by DOMSerializer when the node has no child content.
     * The label is html-escaped to prevent stored-XSS when the output is
     * rendered with {!! !!} in <atom:tiptap.content>.
     */
    public function renderText($node)
    {
        $label = $node->attrs->label ?? $node->attrs->id ?? '';

        return '@'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }
}
