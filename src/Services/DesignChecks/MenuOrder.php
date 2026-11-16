<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\ActionPlacement;
use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class MenuOrder extends ActionRule
{
    public const ID = 'menu-order';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Actions';

    /**
     * The tags that hold menu items themselves
     *
     * @var array<int, string>
     */
    protected const MENUS = ['atom:menu', 'atom:table.actions'];

    /**
     * The `variant` of an item by the tone it draws
     *
     * @var array<string, string>
     */
    protected const TONES = ['default' => 'default', 'warning' => 'warning', 'danger' => 'danger', 'delete' => 'danger', 'remove' => 'danger'];

    public function title(): string
    {
        return 'A menu out of order';
    }

    public function fix(): string
    {
        return 'Order a menu utilities first, then `<atom:menu.separator>`, then warning, then danger last; separate with `<atom:menu.separator>`, never `<atom:separator>` or `<hr>`. A host item that must come after an action\'s rows becomes an `<atom:action tone="warning">`.';
    }

    public function rule(): string
    {
        return 'with `<atom:menu.separator>` before the first warning item and danger last';
    }

    public function detect(): string
    {
        return 'In a `menu` slot (of `page.header`, `table.toolbar`, `card`, `panel`), the table\'s `checked-menu` slot, `<atom:table.actions>` and `<atom:menu>`: an `<atom:menu.item>` with no `variant` after a warning or danger one, a `warning` after a `danger`, the first warning or danger item right after a default one with no `<atom:menu.separator>` between, and an `<atom:separator>` or `<hr>` among the items. Items in the branches of one `@if` chain are not compared, and an item with a bound or other `variant` is not read. In a surface that also holds `<atom:action>`s, the rows `ActionPlacement::place()` puts in its menu are appended after the host\'s items, as they print (with the separator it draws), and the same rules run: a finding stays on the host\'s item, never on an action\'s row (a host warning item before an action\'s utility row is flagged on the host item).';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $menus = [];

        foreach ($outline->nodes() as $node) {
            $kind = $this->kind($node);

            if ($kind === null) {
                continue;
            }

            $menu = $this->menu($outline, $node);

            if ($menu !== null) {
                $menus[$menu['id']][] = [$node, $kind];
            }
        }

        foreach ($menus as $menuId => $entries) {
            $seen = [];

            foreach ($this->printedAfter($outline, $outline->node($menuId)) ?: [[]] as $rows) {
                foreach ($this->order($outline, $entries, $rows) as $finding) {
                    if (! isset($seen[$finding[0].$finding[1]])) {
                        $seen[$finding[0].$finding[1]] = true;

                        yield $finding;
                    }
                }
            }
        }
    }

    /**
     * What a surface prints after the host's own items in a menu, one list for each set of actions that can show together: the menu's rows from
     * `ActionPlacement::place()` as `[action node, tone]` (tone `default`, `warning` or `danger`), with `null` where the separator is drawn. Empty
     * when the menu is not a surface's own, or the surface has no actions it can read.
     *
     * @return array<int, array<int, array{0: array, 1: ?string}>>
     */
    protected function printedAfter(BladeOutline $outline, array $menu): array
    {
        $owner = $menu['kind'] === 'slot' ? $outline->owner($menu) : $menu;

        if ($owner === null || ! isset(static::PLACING[$owner['tag']])) {
            return [];
        }

        $own = match ($owner['tag']) {
            'atom:table.actions' => null,
            'atom:table' => 'checkedmenu',
            default => 'menu',
        };

        if (($menu['kind'] === 'slot' ? $this->slotKey($menu) : null) !== $own) {
            return [];
        }

        $actions = $this->actionsOf($outline, $owner, true);
        $context = $actions === [] ? null : $this->contextOf($outline, $owner);

        if ($context === null || count($actions) > 50) {
            return [];
        }

        $byId = [];

        foreach ($actions as $action) {
            $byId[$action['node']['id']] = $action;
        }

        $lists = [];

        foreach ($this->chains($outline, array_column($actions, 'node')) as $chain) {
            $placed = ActionPlacement::place($this->surfaceOf($owner), array_map(fn ($node) => $byId[$node['id']], $chain), [...$context, 'hostMenu' => true]);
            $rows = [];

            foreach ($placed['menu'] as $at => $row) {
                if ($placed['separator'] === $at) {
                    $rows[] = [$row['node'], null];
                }

                $rows[] = [$row['node'], match ($row['tone']) {
                    null => 'default',
                    'warning' => 'warning',
                    default => 'danger',
                }];
            }

            $lists[] = $rows;
        }

        return $lists;
    }

    /**
     * What a node is in a menu: an `item`, a `separator` or a hand-built one (`hand`)
     */
    protected function kind(array $node): ?string
    {
        return match ($node['tag']) {
            'atom:menu.item' => 'item',
            'atom:menu.separator' => 'separator',
            'atom:separator', 'hr' => 'hand',
            default => null,
        };
    }

    /**
     * The menu an item is in: the nearest menu around it, with nothing but markup between
     */
    protected function menu(BladeOutline $outline, array $node): ?array
    {
        foreach ($outline->ancestors($node) as $ancestor) {
            if ($this->isMenu($outline, $ancestor)) {
                return $this->reaches($outline, $node, $ancestor) ? $ancestor : null;
            }
        }

        return null;
    }

    /**
     * Whether a tag holds menu items: a `menu` slot of a surface, the bulk bar's `checked-menu`, a row's actions, or a menu
     */
    protected function isMenu(BladeOutline $outline, array $node): bool
    {
        if (in_array($node['tag'], static::MENUS, true)) {
            return true;
        }

        if ($node['kind'] !== 'slot') {
            return false;
        }

        $owner = $outline->owner($node)['tag'] ?? null;

        return ($this->slotKey($node) === 'menu' && in_array($owner, ['atom:page.header', 'atom:table.toolbar', 'atom:card', 'atom:panel'], true))
            || ($this->slotKey($node) === 'checkedmenu' && $owner === 'atom:table');
    }

    /**
     * @param  array<int, array{0: array, 1: string}>  $entries  a menu's nodes, in order
     * @param  array<int, array{0: array, 1: ?string}>  $printed  what the surface prints after them (see `printedAfter()`): never reported, but the host's items are judged against it
     * @return iterable<int, array{0: int, 1: string}>
     */
    protected function order(BladeOutline $outline, array $entries, array $printed = []): iterable
    {
        $toned = [];
        $previous = null;
        $separated = false;

        foreach ([...array_map(fn ($entry) => [...$entry, null], $entries), ...array_map(fn ($row) => [$row[0], $row[1] === null ? 'separator' : 'item', $row[1]], $printed)] as $at => [$node, $kind, $given]) {
            $drawn = $at >= count($entries);

            if ($kind === 'hand') {
                yield [$node['offset'], 'a hand-built separator in a menu: use `<atom:menu.separator>`'];
            }

            if ($kind !== 'item') {
                $separated = true;

                continue;
            }

            $tone = $given ?? static::TONES[$outline->value($node, 'variant') ?? ($outline->has($node, 'variant') ? '' : 'default')] ?? null;
            $note = null;
            $before = null;

            if ($tone === 'default' && ($before = $this->follows($outline, $node, $toned, ['warning', 'danger'])) !== null) {
                $note = 'a default item after a warning or danger one: utilities come first';
            } elseif ($tone === 'warning' && ($before = $this->follows($outline, $node, $toned, ['danger'])) !== null) {
                $note = 'a warning item after a danger one: danger goes last';
            } elseif (in_array($tone, ['warning', 'danger'], true) && ! $separated && $previous !== null && $previous[1] === 'default' && $outline->together($previous[0], $node) && $this->follows($outline, $node, $toned, ['warning', 'danger']) === null) {
                $note = 'the first warning or danger item follows a default one: put `<atom:menu.separator>` before it';
            }

            if ($note !== null && ! $drawn) {
                yield [$node['offset'], $note];
            } elseif ($note !== null && $before !== null && $before[2] === null) {
                yield [$before[0]['offset'], 'a '.$before[1].' item before the actions\' rows: atom prints them after your items, so it is not last; make it `<atom:action tone="'.$before[1].'">`'];
            }

            if (in_array($tone, ['warning', 'danger'], true)) {
                $toned[] = [$node, $tone, $drawn ? $given : null];
            }

            $previous = [$node, $tone];
            $separated = false;
        }
    }

    /**
     * The earlier item of one of these tones that can show with this one (the first), or null
     *
     * @param  array<int, array{0: array, 1: string, 2: ?string}>  $toned  `[node, tone, tone it was drawn with or null for a host item]`
     * @param  array<int, string>  $tones
     * @return array{0: array, 1: string, 2: ?string}|null
     */
    protected function follows(BladeOutline $outline, array $node, array $toned, array $tones): ?array
    {
        foreach ($toned as $earlier) {
            if (in_array($earlier[1], $tones, true) && $outline->together($earlier[0], $node)) {
                return $earlier;
            }
        }

        return null;
    }
}
