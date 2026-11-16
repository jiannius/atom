<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class OwnSidebarToggle extends PageRule
{
    public const ID = 'own-sidebar-toggle';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The icons of a sidebar toggle
     *
     * @var array<int, string>
     */
    protected const GLYPHS = ['panel-left', 'panel-left-open', 'panel-left-close'];

    public function title(): string
    {
        return 'A hand-built sidebar toggle';
    }

    public function fix(): string
    {
        return 'The shell draws the toggle (`<atom:layouts.sidebar>`: the circle on the sidebar\'s edge, Ctrl or Cmd+B); remove yours.';
    }

    public function rule(): string
    {
        return 'Never hand-build a title, crumb strip, sidebar toggle';
    }

    public function detect(): string
    {
        return 'A tag that listens for or sends the shell\'s `toggle-sidebar` event (`x-on:toggle-sidebar`, `@toggle-sidebar`, a `$dispatch(\'toggle-sidebar\')` in an attribute), a button with `icon="panel-left"`, `panel-left-open` or `panel-left-close`, or a button whose only content is one of those icons. A `menu` icon (a host\'s own nav button) is not one. A dispatch from a script or from PHP is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            $note = $this->event($node) ?? $this->glyph($file, $node);

            if ($note !== null) {
                yield [$node['offset'], $note];
            }
        }
    }

    /**
     * The `toggle-sidebar` event a tag sends or listens for, as a note
     */
    protected function event(array $node): ?string
    {
        foreach ($node['attributes'] as $name => $value) {
            if (preg_match('/^(?:x-on:|@)toggle-sidebar(?:\.|$)/i', (string) $name)) {
                return 'listens for `toggle-sidebar`';
            }

            if (is_string($value) && preg_match('/(?:\$dispatch|dispatch|CustomEvent|Event)\s*\(\s*[\'"]toggle-sidebar[\'"]/', $value)) {
                return 'sends `toggle-sidebar`';
            }
        }

        return null;
    }

    /**
     * A button drawn as the panel icon: its `icon`, or its only content
     */
    protected function glyph(File $file, array $node): ?string
    {
        if ($node['tag'] !== 'atom:button' && $node['tag'] !== 'button') {
            return null;
        }

        $outline = $file->outline();
        $icon = $outline->value($node, 'icon');

        if ($icon !== null && in_array($icon, static::GLYPHS, true)) {
            return 'a button with `icon="'.$icon.'"`';
        }

        $children = $outline->children($node);

        if ($children === [] || $outline->label($node) !== '') {
            return null;
        }

        foreach ($children as $child) {
            if (! in_array($child['component'], array_map(fn ($glyph) => 'icon.'.$glyph, static::GLYPHS), true)) {
                return null;
            }
        }

        return 'a button drawn as the panel icon';
    }
}
