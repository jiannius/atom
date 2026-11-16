<?php

use Jiannius\Atom\Services\ActionPlacement;

// ActionPlacement::place() is pure: the engine's one rule for caps, order, the primary and the separator, shared with atom:design-check.
// Edge rows are written by hand; invariants I1 to I9 run over a seeded random corpus (its own Randomizer, so it never reseeds the global one).

/** An item with an id, for reading the result back */
function actionItem(string $id, string $slot = 'actions', ?string $priority = null, ?string $tone = null, int $position = 0): array
{
    return compact('id', 'slot', 'priority', 'tone', 'position');
}

/** @return list<string> */
function actionIds(array $items): array
{
    return array_map(fn (array $item) => $item['id'], $items);
}

/** The result as a plain summary: `p`, visible, pinned, menu with `|` where the separator goes */
function actionSummary(array $placed): array
{
    $menu = actionIds($placed['menu']);

    if ($placed['separator'] !== null) {
        array_splice($menu, $placed['separator'], 0, '|');
    }

    return [
        'primary' => $placed['primary']['id'] ?? null,
        'visible' => actionIds($placed['visible']),
        'pinned' => actionIds($placed['pinned']),
        'menu' => $menu,
    ];
}

/** @return list<array> n untoned normal items a, b, c ... in the `actions` slot */
function normals(int $n): array
{
    return array_map(fn ($i) => actionItem(chr(97 + $i), position: $i), range(0, $n - 1));
}

describe('the caps', function () {
    it('cuts the visible buttons at each surface\'s cap and sends the rest to the menu in order', function (string $surface, bool $small, int $cap) {
        $placed = actionSummary(ActionPlacement::place($surface, normals(5), ['small' => $small]));

        expect($placed['visible'])->toBe(array_slice(['a', 'b', 'c', 'd', 'e'], 0, $cap))
            ->and($placed['menu'])->toBe(array_slice(['a', 'b', 'c', 'd', 'e'], $cap))
            ->and($placed['primary'])->toBeNull()
            ->and($placed['pinned'])->toBe([]);
    })->with([
        'header' => ['header', false, 2],
        'header sm' => ['header', true, 1],
        'toolbar' => ['toolbar', false, 2],
        'bulk bar' => ['bulk', false, 3],
        'card' => ['card', false, 1],
        'panel' => ['panel', false, 1],
        'row' => ['row', false, 0],
    ]);

    it('draws nothing at all for no actions', function () {
        expect(actionSummary(ActionPlacement::place('header', [])))->toBe(['primary' => null, 'visible' => [], 'pinned' => [], 'menu' => []]);
    });

    it('fits fewer actions than the cap without a menu', function () {
        expect(actionSummary(ActionPlacement::place('header', normals(2))))->toBe(['primary' => null, 'visible' => ['a', 'b'], 'pinned' => [], 'menu' => []]);
    });
});

describe('the primary', function () {
    it('is the first primary action, whatever slot it is in, and any other primary is a normal one', function () {
        $items = [actionItem('a', position: 0), actionItem('p1', 'actions', 'primary', position: 1), actionItem('p2', 'actions', 'primary', position: 2)];
        $placed = ActionPlacement::place('header', $items);

        expect(actionSummary($placed))->toBe(['primary' => 'p1', 'visible' => ['a', 'p2'], 'pinned' => [], 'menu' => []])
            ->and($placed['visible'][1]['priority'])->toBe('normal');
    });

    it('is the `primary` slot\'s default, and does not count against the visible cap', function () {
        $items = [...normals(3), actionItem('p', 'primary', position: 0)];

        expect(actionSummary(ActionPlacement::place('header', $items)))->toBe(['primary' => 'p', 'visible' => ['a', 'b'], 'pinned' => [], 'menu' => ['c']]);
    });

    it('is not taken by a size="sm" header, a host\'s own primary slot, or a surface that takes none', function (string $surface, array $context) {
        $placed = actionSummary(ActionPlacement::place($surface, [actionItem('p', 'actions', 'primary')], $context));

        expect($placed['primary'])->toBeNull()
            ->and([...$placed['visible'], ...$placed['menu']])->toBe(['p']);
    })->with([
        'sm' => ['header', ['small' => true]],
        'host primary' => ['header', ['hostPrimary' => true]],
        'toolbar' => ['toolbar', []],
        'bulk' => ['bulk', []],
        'card' => ['card', []],
        'row' => ['row', []],
    ]);

    it('is never toned: a toned primary goes to the menu in its tone group, and the next untoned primary is the header\'s', function () {
        $items = [actionItem('v', 'primary', tone: 'warning'), actionItem('d', 'actions', 'primary', 'delete'), actionItem('p', 'actions', 'primary', position: 1), ...normals(1)];
        $placed = ActionPlacement::place('header', $items);

        expect(actionSummary($placed))->toBe(['primary' => 'p', 'visible' => ['a'], 'pinned' => [], 'menu' => ['v', 'd']]);

        $none = ActionPlacement::place('header', [actionItem('v', 'primary', tone: 'danger')]);

        expect(actionSummary($none))->toBe(['primary' => null, 'visible' => [], 'pinned' => [], 'menu' => ['v']])
            ->and($none['menu'][0]['priority'])->toBe('normal');
    });

    it('goes to the toned menu rows when it is demoted', function () {
        $placed = ActionPlacement::place('toolbar', [actionItem('v', 'actions', 'primary', 'danger'), actionItem('a', position: 1)]);

        expect(actionSummary($placed))->toBe(['primary' => null, 'visible' => ['a'], 'pinned' => [], 'menu' => ['v']]);
    });
});

describe('tone and the menu', function () {
    it('never draws a toned action as a button, and orders the menu: untoned, low, separator, warning, then danger and delete', function () {
        $items = [
            actionItem('del', tone: 'delete', position: 0),
            actionItem('dan', tone: 'danger', position: 1),
            actionItem('war', tone: 'warning', position: 2),
            actionItem('low', priority: 'low', position: 3),
            actionItem('n1', position: 4),
            actionItem('n2', position: 5),
            actionItem('n3', position: 6),
        ];

        expect(actionSummary(ActionPlacement::place('header', $items)))->toBe([
            'primary' => null, 'visible' => ['n1', 'n2'], 'pinned' => [], 'menu' => ['n3', 'low', '|', 'war', 'del', 'dan'],
        ]);
    });

    it('keeps the slot order inside a group: the host\'s `actions` before its `menu`', function () {
        $items = [actionItem('m', 'menu', 'normal', position: 0), actionItem('a', 'actions', position: 0)];

        expect(actionSummary(ActionPlacement::place('header', $items))['visible'])->toBe(['a', 'm']);
    });

    it('draws no separator when nothing precedes the toned rows, and one when the host\'s own menu does', function () {
        $toned = [actionItem('w', tone: 'warning'), actionItem('d', tone: 'delete', position: 1)];

        expect(actionSummary(ActionPlacement::place('header', $toned))['menu'])->toBe(['w', 'd'])
            ->and(actionSummary(ActionPlacement::place('header', $toned, ['hostMenu' => true]))['menu'])->toBe(['|', 'w', 'd'])
            ->and(actionSummary(ActionPlacement::place('header', [...$toned, actionItem('l', priority: 'low', position: 2)]))['menu'])->toBe(['l', '|', 'w', 'd']);
    });

    it('draws no separator for a host menu alone, or after the last row', function () {
        expect(ActionPlacement::place('header', normals(4), ['hostMenu' => true])['separator'])->toBeNull();
    });

    it('takes the slot\'s default priority: `menu` and `checked-menu` are low, so an untoned item there stays out of the buttons', function () {
        $placed = ActionPlacement::place('header', [actionItem('m', 'menu'), actionItem('c', 'checked-menu', position: 1), actionItem('n', 'actions')]);

        expect(actionSummary($placed)['visible'])->toBe(['n'])
            ->and(actionIds($placed['menu']))->toBe(['m', 'c'])
            ->and($placed['menu'][0]['priority'])->toBe('low');
    });

    it('lets an explicit `normal` in a menu slot surface as a button', function () {
        expect(actionSummary(ActionPlacement::place('header', [actionItem('m', 'menu', 'normal')]))['visible'])->toBe(['m']);
    });
});

describe('the bulk bar\'s pin', function () {
    it('keeps the first danger or delete action visible, last, outside the 3', function () {
        $items = [...normals(4), actionItem('war', tone: 'warning', position: 4), actionItem('dan', tone: 'danger', position: 5), actionItem('del', tone: 'delete', position: 6)];

        expect(actionSummary(ActionPlacement::place('bulk', $items)))->toBe([
            'primary' => null, 'visible' => ['a', 'b', 'c'], 'pinned' => ['dan'], 'menu' => ['d', '|', 'war', 'del'],
        ]);
    });

    it('does not pin a warning action, a low one, or a delete on any other surface', function () {
        expect(actionSummary(ActionPlacement::place('bulk', [actionItem('w', tone: 'warning')]))['pinned'])->toBe([])
            ->and(actionSummary(ActionPlacement::place('bulk', [actionItem('d', 'checked-menu', tone: 'delete')]))['pinned'])->toBe([])
            ->and(actionSummary(ActionPlacement::place('bulk', [actionItem('d', tone: 'delete', priority: 'low')]))['pinned'])->toBe([]);

        foreach (['header', 'toolbar', 'card', 'panel', 'row'] as $surface) {
            expect(ActionPlacement::place($surface, [actionItem('d', tone: 'delete')])['pinned'])->toBe([]);
        }
    });
});

describe('what it refuses', function () {
    it('throws for an unknown surface, priority or tone', function () {
        expect(fn () => ActionPlacement::place('footer', []))->toThrow(InvalidArgumentException::class, 'Unknown action surface')
            ->and(fn () => ActionPlacement::place('header', [actionItem('a', priority: 'high')]))->toThrow(InvalidArgumentException::class, 'Unknown action priority')
            ->and(fn () => ActionPlacement::place('header', [actionItem('a', tone: 'success')]))->toThrow(InvalidArgumentException::class, 'Unknown action tone');
    });

    it('keeps any other key on an item, and hands it back resolved', function () {
        $placed = ActionPlacement::place('header', [[...actionItem('a'), 'label' => 'Edit', 'key' => 'k']]);

        expect($placed['visible'][0])->toMatchArray(['label' => 'Edit', 'key' => 'k', 'priority' => 'normal']);
    });
});

describe('the invariants, over a seeded random corpus', function () {
    /** @return list<array<string, mixed>> */
    function randomItems(\Random\Randomizer $random): array
    {
        $slots = ActionPlacement::SLOT_ORDER;
        $positions = [];
        $items = [];

        foreach (range(1, $random->getInt(0, 9)) as $n) {
            $slot = $slots[$random->getInt(0, count($slots) - 1)];
            $positions[$slot] = ($positions[$slot] ?? -1) + 1;
            $items[] = actionItem(
                'i'.$n,
                $slot,
                [null, null, 'primary', 'normal', 'low'][$random->getInt(0, 4)],
                [null, null, null, 'warning', 'danger', 'delete'][$random->getInt(0, 5)],
                $positions[$slot],
            );
        }

        shuffle_with($random, $items);

        return $items;
    }

    function shuffle_with(\Random\Randomizer $random, array &$items): void
    {
        $items = $random->shuffleArray($items);
    }

    function slotRank(array $item): array
    {
        return [array_search($item['slot'], ActionPlacement::SLOT_ORDER, true), $item['position']];
    }

    /** @param list<array> $items  true when they are in slot order, then position order */
    function isOrdered(array $items): bool
    {
        for ($i = 1; $i < count($items); $i++) {
            if (slotRank($items[$i - 1]) > slotRank($items[$i])) {
                return false;
            }
        }

        return true;
    }

    it('holds I1 to I9 for every surface, size and host context', function () {
        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(20261116));

        for ($run = 0; $run < 1500; $run++) {
            $surface = ActionPlacement::SURFACES[$random->getInt(0, count(ActionPlacement::SURFACES) - 1)];
            $context = ['small' => (bool) $random->getInt(0, 1), 'hostPrimary' => (bool) $random->getInt(0, 1), 'hostMenu' => (bool) $random->getInt(0, 1)];
            $items = randomItems($random);
            $caps = ActionPlacement::caps($surface, $context['small']);
            $placed = ActionPlacement::place($surface, $items, $context);
            $why = json_encode(compact('surface', 'context', 'items'));

            // I1: at most the cap visible; the pin is the bulk bar's alone
            expect(count($placed['visible']))->toBeLessThanOrEqual($caps['visible'], $why)
                ->and(count($placed['pinned']))->toBeLessThanOrEqual($caps['pinned'], $why);

            // I2: at most one primary, only where one is taken, and never a toned one
            expect($placed['primary'] === null || ($caps['primary'] > 0 && ! $context['hostPrimary'] && $placed['primary']['tone'] === null))->toBeTrue($why);

            // I3: no toned action is a visible button
            foreach ($placed['visible'] as $item) {
                expect($item['tone'])->toBeNull($why);
            }

            foreach ($placed['pinned'] as $item) {
                expect(in_array($item['tone'], ['danger', 'delete'], true) && $item['priority'] !== 'low')->toBeTrue($why);
            }

            // I4: the menu's groups never go backwards: untoned, then warning, then danger and delete
            $rank = fn (array $item) => match ($item['tone']) { null => 0, 'warning' => 1, default => 2 };
            $ranks = array_map($rank, $placed['menu']);
            $sorted = $ranks;
            sort($sorted);
            expect($ranks)->toBe($sorted, $why);

            // I5: a separator exactly when toned rows follow something
            $untoned = count(array_filter($ranks, fn ($r) => $r === 0));
            $toned = count($ranks) - $untoned;
            $expected = $toned > 0 && ($untoned > 0 || $context['hostMenu']) ? $untoned : null;
            expect($placed['separator'])->toBe($expected, $why);

            // I6: every item lands exactly once
            $landed = [...actionIds($placed['primary'] ? [$placed['primary']] : []), ...actionIds($placed['visible']), ...actionIds($placed['pinned']), ...actionIds($placed['menu'])];
            $all = actionIds($items);
            sort($landed);
            sort($all);
            expect($landed)->toBe($all, $why);

            // I8: order is kept within each group
            expect(isOrdered($placed['visible']))->toBeTrue($why);

            foreach ([null, 'warning'] as $tone) {
                foreach (['normal', 'low'] as $priority) {
                    expect(isOrdered(array_values(array_filter($placed['menu'], fn ($i) => $i['tone'] === $tone && $i['priority'] === $priority))))->toBeTrue($why);
                }
            }

            expect(isOrdered(array_values(array_filter($placed['menu'], fn ($i) => in_array($i['tone'], ['danger', 'delete'], true)))))->toBeTrue($why);

            // I9: deterministic
            expect(ActionPlacement::place($surface, $items, $context))->toBe($placed, $why);

            // I7: a low or toned action added later never changes the visible buttons; a normal one added last only takes a free place
            $visible = actionIds($placed['visible']);
            $extra = [
                actionItem('x', 'checked-menu', 'low', position: 99),
                actionItem('x', 'checked-menu', 'normal', 'warning', position: 99),
                actionItem('x', 'checked-menu', 'normal', 'danger', position: 99),
            ][$random->getInt(0, 2)];
            expect(actionIds(ActionPlacement::place($surface, [...$items, $extra], $context)['visible']))->toBe($visible, $why);

            $last = actionItem('x', 'checked-menu', 'normal', position: 99);
            $after = actionIds(ActionPlacement::place($surface, [...$items, $last], $context)['visible']);
            expect($after === $visible || $after === [...$visible, 'x'])->toBeTrue($why);
        }
    });
});

describe('one source of truth for the caps', function () {
    it('matches the numbers the guideline and the skill print, and the ones design-check already counts', function () {
        $guideline = file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php');
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');

        expect(preg_match('/- Caps: header `actions` (\d+) \(`size="sm"` (\d+)\), toolbar (\d+), bulk bar (\d+), card heading (\d+)/', $guideline, $line))->toBe(1);

        [, $header, $small, $toolbar, $bulk, $card] = array_map('intval', $line);

        expect(ActionPlacement::CAPS['header']['visible'])->toBe($header)
            ->and(ActionPlacement::CAPS['header-sm']['visible'])->toBe($small)
            ->and(ActionPlacement::CAPS['toolbar']['visible'])->toBe($toolbar)
            ->and(ActionPlacement::CAPS['bulk']['visible'])->toBe($bulk)
            ->and(ActionPlacement::CAPS['card']['visible'])->toBe($card);

        $rows = [];

        foreach (['`page.header`' => 'header', '`page.header size="sm"` (modal, slide-over)' => 'header-sm', '`table.toolbar`' => 'toolbar', 'Bulk bar (`checked`)' => 'bulk', 'Card or panel heading' => 'card'] as $label => $caps) {
            expect(preg_match('/^\| '.preg_quote($label, '/').' \|.*?\| at most (\d+)/m', $skill, $row))->toBe(1, $label);
            $rows[$caps] = (int) $row[1];
        }

        foreach ($rows as $caps => $number) {
            expect(ActionPlacement::CAPS[$caps]['visible'])->toBe($number, $caps);
        }

        expect(ActionPlacement::CAPS['card']['visible'])->toBe(ActionPlacement::CAPS['panel']['visible'])
            ->and(ActionPlacement::CAPS['row']['visible'])->toBe(0);

        // the phase 4 check counts the same places
        $surfaces = (new ReflectionClassConstant(\Jiannius\Atom\Services\DesignChecks\ActionCap::class, 'SURFACES'))->getValue();
        $counted = collect($surfaces)->mapWithKeys(fn ($row) => [$row[0][0] => $row[2]])->all();

        expect($counted['atom:page.header'])->toBe($header)
            ->and($counted['atom:table.toolbar'])->toBe($toolbar)
            ->and($counted['atom:table'])->toBe($bulk)
            ->and($counted['atom:card'])->toBe($card)
            ->and($counted['atom:panel'])->toBe(ActionPlacement::CAPS['panel']['visible']);
    });
});
