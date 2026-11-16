<?php

namespace Jiannius\Atom\Services\DesignChecks;

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
        return 'Order a menu utilities first, then `<atom:menu.separator>`, then warning, then danger last; separate with `<atom:menu.separator>`, never `<atom:separator>` or `<hr>`.';
    }

    public function rule(): string
    {
        return 'with `<atom:menu.separator>` before the first warning item and danger last';
    }

    public function detect(): string
    {
        return 'In a `menu` slot (of `page.header`, `table.toolbar`, `card`, `panel`), the table\'s `checked-menu` slot, `<atom:table.actions>` and `<atom:menu>`: an `<atom:menu.item>` with no `variant` after a warning or danger one, a `warning` after a `danger`, the first warning or danger item right after a default one with no `<atom:menu.separator>` between, and an `<atom:separator>` or `<hr>` among the items. Items in the branches of one `@if` chain are not compared, and an item with a bound or other `variant` is not read.';
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

        foreach ($menus as $entries) {
            yield from $this->order($outline, $entries);
        }
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
     * @return iterable<int, array{0: int, 1: string}>
     */
    protected function order(BladeOutline $outline, array $entries): iterable
    {
        $toned = [];
        $previous = null;
        $separated = false;

        foreach ($entries as [$node, $kind]) {
            if ($kind === 'hand') {
                yield [$node['offset'], 'a hand-built separator in a menu: use `<atom:menu.separator>`'];
            }

            if ($kind !== 'item') {
                $separated = true;

                continue;
            }

            $tone = static::TONES[$outline->value($node, 'variant') ?? ($outline->has($node, 'variant') ? '' : 'default')] ?? null;
            $note = null;

            if ($tone === 'default' && $this->follows($outline, $node, $toned, ['warning', 'danger'])) {
                $note = 'a default item after a warning or danger one: utilities come first';
            } elseif ($tone === 'warning' && $this->follows($outline, $node, $toned, ['danger'])) {
                $note = 'a warning item after a danger one: danger goes last';
            } elseif (in_array($tone, ['warning', 'danger'], true) && ! $separated && $previous !== null && $previous[1] === 'default' && $outline->together($previous[0], $node) && ! $this->follows($outline, $node, $toned, ['warning', 'danger'])) {
                $note = 'the first warning or danger item follows a default one: put `<atom:menu.separator>` before it';
            }

            if ($note !== null) {
                yield [$node['offset'], $note];
            }

            if (in_array($tone, ['warning', 'danger'], true)) {
                $toned[] = [$node, $tone];
            }

            $previous = [$node, $tone];
            $separated = false;
        }
    }

    /**
     * Whether an earlier item of one of these tones can show with this one
     *
     * @param  array<int, array{0: array, 1: string}>  $toned
     * @param  array<int, string>  $tones
     */
    protected function follows(BladeOutline $outline, array $node, array $toned, array $tones): bool
    {
        foreach ($toned as [$earlier, $tone]) {
            if (in_array($tone, $tones, true) && $outline->together($earlier, $node)) {
                return true;
            }
        }

        return false;
    }
}
