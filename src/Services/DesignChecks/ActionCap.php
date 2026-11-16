<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class ActionCap extends ActionRule
{
    public const ID = 'action-cap';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Actions';

    /**
     * The tags that count as one visible action: a button, a button group and a dropdown each count once
     *
     * @var array<int, string>
     */
    protected const UNITS = ['atom:button', 'atom:button.group', 'atom:dropdown'];

    /**
     * The surfaces: the owner's tag, the slots that fill it (folded names), the cap and the name in the note
     *
     * @var array<int, array{0: array<int, string>, 1: array<int, string>, 2: int, 3: string}>
     */
    protected const SURFACES = [
        [['atom:page.header'], ['actions'], 2, 'page.header actions'],
        [['atom:table.toolbar'], ['actions', 'end'], 2, 'table.toolbar actions'],
        [['atom:table'], ['checked'], 3, 'bulk bar (checked slot)'],
        [['atom:card'], ['actions'], 1, 'card actions'],
        [['atom:panel'], ['actions'], 1, 'panel actions'],
        [['atom:empty'], ['action'], 1, 'empty state action'],
    ];

    public function title(): string
    {
        return 'More visible actions than the surface holds';
    }

    public function fix(): string
    {
        return 'Keep the first ones and move the rest to the surface\'s `menu` slot (`checked-menu` on a bulk bar), utilities first, then `<atom:menu.separator>`, warning, and danger last, as `<atom:menu.item>`s.';
    }

    public function rule(): string
    {
        return 'Caps: header `actions` 2';
    }

    public function detect(): string
    {
        return 'The buttons in a surface\'s own slot (nothing but markup between), counting a `<atom:button.group>` or an `<atom:dropdown>` as one: `page.header` `actions` over 2 (over 1 with `size="sm"`), `table.toolbar` `actions` plus `end` over 2, the table\'s `checked` slot over 3 (a delete in the last place is not counted), `card` or `panel` `actions` over 1, `empty` `action` over 1. Buttons that cannot show together (the branches of one `@if` chain) are not counted together, and a button in a loop counts once. The shell\'s own `actions` slot is never counted, and neither is a button inside a component.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        if ($outline->branchesDeclined()) {
            return;
        }

        foreach (static::SURFACES as [$owners, $names, $base, $name]) {
            $units = [];

            foreach ($this->slots($outline, $owners, $names) as $slot) {
                $owner = $outline->owner($slot);
                $units[$owner['id']] = array_merge($units[$owner['id']] ?? [], $this->units($outline, $slot));
            }

            foreach ($units as $ownerId => $found) {
                $cap = $this->capFor($outline, $outline->node($ownerId), $base);

                if ($cap === null) {
                    continue;
                }

                usort($found, fn ($a, $b) => $a['offset'] <=> $b['offset']);

                if ($name === 'bulk bar (checked slot)' && $found !== [] && $this->isDelete($outline, end($found))) {
                    array_pop($found);
                }

                $chain = $this->largestTogether($outline, $found);

                if (count($chain) > $cap) {
                    yield [$chain[$cap]['offset'], $name.': '.count($chain).' (cap '.$cap.')'];
                }
            }
        }
    }

    /**
     * The cap of a surface: a `page.header size="sm"` holds 1 button, not 2; null when the header's `size` is bound (not read)
     */
    protected function capFor(BladeOutline $outline, array $owner, int $cap): ?int
    {
        if ($owner['tag'] !== 'atom:page.header' || ! $outline->has($owner, 'size')) {
            return $cap;
        }

        $size = $outline->value($owner, 'size');

        return $size === null ? null : ($size === 'sm' ? 1 : $cap);
    }

    /**
     * The actions in a slot: each button, group or dropdown that is the slot's own, outermost first
     *
     * @return array<int, array>
     */
    protected function units(BladeOutline $outline, array $slot): array
    {
        return array_values(array_filter(
            $outline->descendants($slot),
            fn ($node) => in_array($node['tag'], static::UNITS, true) && $this->reaches($outline, $node, $slot),
        ));
    }

    /**
     * Whether the action is a delete: `type="delete"`, or a danger or delete `variant`
     */
    protected function isDelete(BladeOutline $outline, array $unit): bool
    {
        return $unit['tag'] === 'atom:button' && ($outline->value($unit, 'type') === 'delete' || in_array($outline->value($unit, 'variant'), ['danger', 'delete'], true));
    }

    /**
     * The most actions that can show at once, in document order. Two can show together when one's branch path is the
     * start of the other's, so such a set lies along one path: for each action, take those whose path is a prefix of its own.
     *
     * @param  array<int, array>  $units
     * @return array<int, array>
     */
    protected function largestTogether(BladeOutline $outline, array $units): array
    {
        $paths = array_map(fn ($unit) => count($outline->branch($unit)), $units);
        $best = [];

        foreach ($units as $i => $unit) {
            $chain = [];

            foreach ($units as $j => $other) {
                if ($paths[$j] <= $paths[$i] && $outline->together($unit, $other)) {
                    $chain[] = $other;
                }
            }

            if (count($chain) > count($best)) {
                $best = $chain;
            }
        }

        return $best;
    }
}
