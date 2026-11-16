<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class OwnOverflow extends Rule
{
    public const ID = 'own-overflow';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Actions';

    /**
     * The glyphs of a ⋯ trigger
     *
     * @var array<int, string>
     */
    protected const GLYPHS = ['more', 'ellipsis'];

    public function title(): string
    {
        return 'A hand-built ⋯ dropdown';
    }

    public function fix(): string
    {
        return 'Use the surface\'s `menu` slot (`<atom:page.header>`, `<atom:table.toolbar>`, `<atom:card>`, `<atom:panel>`), `<atom:table.actions>` in a row, or the table\'s `checked-menu` slot: they draw the one ⋯ trigger.';
    }

    public function rule(): string
    {
        return 'Never build your own ⋯ dropdown';
    }

    public function detect(): string
    {
        return 'An `<atom:dropdown>` whose trigger (its first `<atom:button>` outside the menu) has `icon="more"` or `icon="ellipsis"`, or holds only an `<atom:icon.more>` or `<atom:icon.ellipsis>`. A trigger built from a bound `icon` or a host component is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('dropdown') as $dropdown) {
            $trigger = $this->trigger($file, $dropdown);

            if ($trigger === null || ! $this->isEllipsis($file, $trigger)) {
                continue;
            }

            yield [$dropdown['offset'], $this->place($file, $dropdown)];
        }
    }

    /**
     * The dropdown's trigger: its first button that is not an item of its menu
     */
    protected function trigger(File $file, array $dropdown): ?array
    {
        $outline = $file->outline();

        foreach ($outline->descendants($dropdown) as $node) {
            if ($node['tag'] !== 'atom:button') {
                continue;
            }

            foreach ($outline->ancestors($node) as $ancestor) {
                if ($ancestor['id'] <= $dropdown['id']) {
                    break;
                }

                if (str_starts_with($ancestor['tag'], 'atom:menu')) {
                    continue 2;
                }
            }

            return $node;
        }

        return null;
    }

    /**
     * Whether a button is a ⋯: the icon prop, or an icon tag as its only content
     */
    protected function isEllipsis(File $file, array $button): bool
    {
        $outline = $file->outline();

        if (in_array($outline->value($button, 'icon'), static::GLYPHS, true)) {
            return true;
        }

        $children = $outline->children($button);

        if ($children === [] || $outline->label($button) !== '') {
            return false;
        }

        foreach ($children as $child) {
            if (! in_array($child['component'], array_map(fn ($glyph) => 'icon.'.$glyph, static::GLYPHS), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The note: where the dropdown sits and what that surface offers instead
     */
    protected function place(File $file, array $dropdown): string
    {
        $outline = $file->outline();

        foreach ($outline->ancestors($dropdown) as $ancestor) {
            $note = match (true) {
                $ancestor['tag'] === 'atom:page.header' => 'in `page.header`: use its `menu` slot',
                $ancestor['tag'] === 'atom:table.toolbar' => 'in `table.toolbar`: use its `menu` slot',
                $ancestor['tag'] === 'atom:card' => 'in a card: use its `menu` slot',
                $ancestor['tag'] === 'atom:panel' => 'in a panel: use its `menu` slot',
                in_array($ancestor['tag'], ['atom:table.actions', 'atom:table.row', 'atom:table.cell'], true) => 'in a table row: use `<atom:table.actions>`',
                $ancestor['kind'] === 'slot' && $ancestor['slot'] === 'checked' && ($outline->owner($ancestor)['tag'] ?? null) === 'atom:table' => 'in the bulk bar: use the table\'s `checked-menu` slot',
                default => null,
            };

            if ($note !== null) {
                return $note;
            }
        }

        return 'use the surface\'s `menu` slot';
    }
}
