<?php

namespace Jiannius\Atom\Services\DesignChecks;

/**
 * What the page and header checks share: the group, a utility class read without its variants, and the boxes
 * (a card, a panel, a table, a form, a modal, a description list) inside which a layout is that box's own and not the page's.
 */
abstract class PageRule extends Rule
{
    public const GROUP = 'Page and header';

    /**
     * The tags that make a box of their own: atom components whose inside is laid out by them, and the HTML
     * elements that are one
     *
     * @var array<int, string>
     */
    protected const BOXES = [
        'atom:card', 'atom:panel', 'atom:table', 'atom:modal', 'atom:dd', 'atom:dd.group', 'atom:form', 'atom:accordion',
        'dd', 'dl', 'table', 'dialog', 'form',
    ];

    /**
     * A utility without its variants (`lg:hover:mt-4` is `mt-4`) and without a leading or trailing important mark; null
     * when a variant sends it to the tag's children (`*:mt-4`, `[&>p]:mb-4`, `[&_p]:mt-3`), which is not the tag's own
     */
    protected function utility(string $token): ?string
    {
        $depth = 0;
        $parts = [];
        $from = 0;

        for ($i = 0, $length = strlen($token); $i < $length; $i++) {
            $char = $token[$i];

            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                $parts[] = substr($token, $from, $i - $from);
                $from = $i + 1;
            }
        }

        $base = substr($token, $from);

        foreach ($parts as $variant) {
            if ($variant === '*' || $variant === '**' || (str_starts_with($variant, '[&') && preg_match('/[>+~_\s]/', $variant))) {
                return null;
            }
        }

        return trim($base, '!');
    }

    /**
     * The utilities of a tag's classes, as written (a bound or echoed token is left out by the outline, and so is one
     * that a variant sends to the tag's children)
     *
     * @return array<int, string>
     */
    protected function utilities(File $file, array $node): array
    {
        return array_values(array_filter(array_map(fn ($token) => $this->utility($token), $file->outline()->classes($node)), fn ($utility) => $utility !== null));
    }

    /**
     * Whether a tag is a box of its own, or a form part or table part
     */
    protected function isBox(array $node): bool
    {
        $tag = $node['tag'];

        return in_array($tag, static::BOXES, true)
            || str_starts_with($tag, 'atom:table.')
            || str_starts_with($tag, 'atom:form.')
            || str_starts_with($tag, 'atom:modal.');
    }

    /**
     * Whether a tag around a node is a box, nearest first, up to (not including) `$stop`: the node itself is not asked
     */
    protected function insideBox(File $file, array $node, ?array $stop = null): bool
    {
        foreach ($file->outline()->ancestors($node) as $ancestor) {
            if ($stop !== null && $ancestor['id'] === $stop['id']) {
                return false;
            }

            if ($this->isBox($ancestor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The nearest `<atom:page>` around a node
     */
    protected function pageOf(File $file, array $node): ?array
    {
        foreach ($file->outline()->ancestors($node) as $ancestor) {
            if ($ancestor['tag'] === 'atom:page') {
                return $ancestor;
            }
        }

        return null;
    }
}
