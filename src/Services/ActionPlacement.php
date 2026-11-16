<?php

namespace Jiannius\Atom\Services;

use InvalidArgumentException;

/**
 * Where each action of a surface goes: a button, the primary, the bulk bar's pinned delete, or a row of the surface's ⋯ menu.
 *
 * Pure: no Blade, no registry, no request. `<atom:action>`'s surfaces call it, and so does `atom:design-check`, so the engine and the
 * checks cannot disagree about a cap, an order or the primary. A cap lives in CAPS and nowhere else; the guideline's caps line and the skill's
 * caps table say the same numbers, and a test ties them (`ActionPlacementTest`).
 *
 * The rule, in order:
 *  1. Normalise: a `primary` goes to the first primary action of a surface that takes one (the header, unless it is `size="sm"` or the host fills its
 *     own `primary` slot); any other primary action is a `normal` one.
 *  2. Visible: the untoned `normal` actions, in order, up to the surface's cap. A toned action is never a visible button, except the bulk bar's pin.
 *  3. Pinned (bulk bar only): the first `danger` or `delete` action that is not `low`, drawn after the visible ones.
 *  4. Menu: the untoned `normal` actions left over, then the `low` ones; then a separator, then `warning`, then `danger` and `delete`. The separator
 *     is drawn only when something precedes the first toned row: an untoned row, or the host's own `menu` markup.
 *  5. Order inside a group: by slot (`SLOT_ORDER`), then by position in the slot.
 */
class ActionPlacement
{
    /**
     * The surfaces that place actions, and the key a `size="sm"` header is looked up under.
     */
    public const SURFACES = ['header', 'toolbar', 'bulk', 'card', 'panel', 'row'];

    /**
     * What each surface holds. `primary` is how many primary actions it takes, `visible` how many untoned buttons it draws besides the primary,
     * `pinned` how many toned buttons it may keep visible (the bulk bar's delete, last: design rules Q6).
     *
     * @var array<string, array{primary: int, visible: int, pinned: int}>
     */
    public const CAPS = [
        'header' => ['primary' => 1, 'visible' => 2, 'pinned' => 0],
        'header-sm' => ['primary' => 0, 'visible' => 1, 'pinned' => 0],
        'toolbar' => ['primary' => 0, 'visible' => 2, 'pinned' => 0],
        'bulk' => ['primary' => 0, 'visible' => 3, 'pinned' => 1],
        'card' => ['primary' => 0, 'visible' => 1, 'pinned' => 0],
        'panel' => ['primary' => 0, 'visible' => 1, 'pinned' => 0],
        'row' => ['primary' => 0, 'visible' => 0, 'pinned' => 0],
    ];

    public const PRIORITIES = ['primary', 'normal', 'low'];

    public const TONES = ['warning', 'danger', 'delete'];

    /**
     * The priority an action has when it does not say: the slot it is written in gives it, so moving a surface is a tag rename.
     */
    public const SLOT_PRIORITY = ['primary' => 'primary', 'menu' => 'low', 'checked-menu' => 'low'];

    /**
     * The order the slots are read in, inside a group.
     */
    public const SLOT_ORDER = ['primary', 'default', 'actions', 'end', 'checked', 'menu', 'checked-menu'];

    /**
     * Place one surface's actions.
     *
     * Each item is an array with `priority` (null for the slot's default), `tone` (null for neutral), `slot` and `position` (its place in the slot);
     * any other key travels with it. The items come back with `priority` resolved, so a primary that was demoted reads `normal`.
     *
     * @param  string  $surface  one of SURFACES
     * @param  list<array<string, mixed>>  $items
     * @param  array{small?: bool, hostPrimary?: bool, hostMenu?: bool}  $context  `small` is a `size="sm"` header, `hostPrimary` the host filling the header's own `primary` slot, `hostMenu` the host's own `menu` markup
     * @return array{primary: ?array, visible: list<array>, pinned: list<array>, menu: list<array>, separator: ?int} `separator` is the index in `menu` the separator goes before, or null
     */
    public static function place(string $surface, array $items, array $context = []): array
    {
        $caps = static::caps($surface, (bool) ($context['small'] ?? false));
        $primaryCap = ($context['hostPrimary'] ?? false) ? 0 : $caps['primary'];

        $primary = null;
        $visible = [];
        $pinned = [];
        $menu = ['normal' => [], 'low' => [], 'warning' => [], 'danger' => []];

        foreach (static::ordered($items) as $item) {
            $item['priority'] = static::priorityOf($item);
            $tone = static::toneOf($item);

            if ($item['priority'] === 'primary') {
                if ($primary === null && $primaryCap > 0) {
                    $primary = $item;

                    continue;
                }

                $item['priority'] = 'normal';
            }

            if ($tone === null) {
                if ($item['priority'] === 'normal' && count($visible) < $caps['visible']) {
                    $visible[] = $item;
                }
                else {
                    $menu[$item['priority']][] = $item;
                }
            }
            elseif ($caps['pinned'] > count($pinned) && $item['priority'] !== 'low' && in_array($tone, ['danger', 'delete'], true)) {
                $pinned[] = $item;
            }
            else {
                $menu[$tone === 'warning' ? 'warning' : 'danger'][] = $item;
            }
        }

        $untoned = [...$menu['normal'], ...$menu['low']];
        $toned = [...$menu['warning'], ...$menu['danger']];

        return [
            'primary' => $primary,
            'visible' => $visible,
            'pinned' => $pinned,
            'menu' => [...$untoned, ...$toned],
            'separator' => $toned !== [] && ($untoned !== [] || ($context['hostMenu'] ?? false)) ? count($untoned) : null,
        ];
    }

    /**
     * The caps of a surface (a `size="sm"` header has its own).
     *
     * @return array{primary: int, visible: int, pinned: int}
     */
    public static function caps(string $surface, bool $small = false): array
    {
        if (! in_array($surface, static::SURFACES, true)) {
            throw new InvalidArgumentException("Unknown action surface [{$surface}]; use one of: ".implode(', ', static::SURFACES).'.');
        }

        return static::CAPS[$surface === 'header' && $small ? 'header-sm' : $surface];
    }

    /**
     * The priority an item has: its own, else the default of its slot.
     */
    public static function priorityOf(array $item): string
    {
        $priority = $item['priority'] ?? static::SLOT_PRIORITY[$item['slot'] ?? ''] ?? 'normal';

        if (! in_array($priority, static::PRIORITIES, true)) {
            throw new InvalidArgumentException('Unknown action priority ['.(is_scalar($priority) ? $priority : gettype($priority)).']; use one of: '.implode(', ', static::PRIORITIES).'.');
        }

        return $priority;
    }

    /**
     * @return ?string  warning, danger or delete; null for a neutral action
     */
    protected static function toneOf(array $item): ?string
    {
        $tone = $item['tone'] ?? null;

        if ($tone !== null && ! in_array($tone, static::TONES, true)) {
            throw new InvalidArgumentException('Unknown action tone ['.(is_scalar($tone) ? $tone : gettype($tone)).']; use one of: '.implode(', ', static::TONES).'.');
        }

        return $tone;
    }

    /**
     * The items by slot, then by position, with the order they came in as the last tie-break so the result is stable.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected static function ordered(array $items): array
    {
        $indexed = [];

        foreach (array_values($items) as $index => $item) {
            $rank = array_search($item['slot'] ?? 'default', static::SLOT_ORDER, true);
            $indexed[] = [$rank === false ? count(static::SLOT_ORDER) : $rank, (int) ($item['position'] ?? 0), $index, $item];
        }

        usort($indexed, fn (array $a, array $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(fn (array $row) => $row[3], $indexed);
    }
}
