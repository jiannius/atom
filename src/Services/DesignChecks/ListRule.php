<?php

namespace Jiannius\Atom\Services\DesignChecks;

/**
 * What the list checks share: a tag's classes read as written, and an attribute read as a literal.
 */
abstract class ListRule extends Rule
{
    /**
     * The class tokens a tag carries in its static `class` attribute. An echo is blanked to a character no class
     * matches (so what is inside it is never read), and a `:class` or `@class` is not read at all: what it adds is conditional, so a check that holds a tag to a
     * class holds it only to the one written outright.
     *
     * @return array<int, string>
     */
    protected function staticClasses(array $node): array
    {
        $class = $node['attributes']['class'] ?? null;

        if (! is_string($class)) {
            return [];
        }

        $marked = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', "\0", $class) ?? $class;

        return preg_split('/\s+/', $marked, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
