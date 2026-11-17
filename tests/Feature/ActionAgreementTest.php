<?php

use Jiannius\Atom\Services\ActionPlacement;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

// The engine and the checks agree on real output, not only on a shared constant: for every case of the placement matrix, what
// ActionPlacement::place() returns is written as the Blade a host would write by hand (buttons in `actions`, the primary in `primary`, menu
// items and `<atom:menu.separator>` in `menu`, the bulk bar's delete last), and the checks that judge it find nothing.

describe('the placement matrix, written by hand, is clean', function () {
    $checks = ['action-cap', 'menu-order', 'extra-primary', 'one-red', 'tone-unconfirmed'];

    /**
     * One placed surface as hand-written Blade: [surface, context, items]
     */
    $write = function (string $surface, array $context, array $items, int $case): string {
        $placed = ActionPlacement::place($surface, $items, $context);
        $small = $context['small'] ?? false;
        $button = fn (array $item, string $attributes = '') => '<atom:button'.$attributes.' wire:click="a'.$item['id'].'">Action '.$item['id'].'</atom:button>';
        $row = function (array $item): string {
            $attributes = match ($item['tone'] ?? null) {
                'warning' => ' variant="warning" confirm',
                'danger' => ' variant="danger" confirm',
                'delete' => ' variant="delete"',
                default => '',
            };

            return '<atom:menu.item'.$attributes.' wire:click="a'.$item['id'].'">Action '.$item['id'].'</atom:menu.item>';
        };

        $visible = array_map(fn ($item) => $button($item, match ($surface) {
            'header' => $small ? ' size="sm"' : '',
            'toolbar', 'card', 'panel' => ' variant="ghost"',
            default => '',
        }), $placed['visible']);

        $pinned = array_map(fn ($item) => $button($item, ($item['tone'] ?? null) === 'delete' ? ' type="delete"' : ' variant="danger" inverted confirm'), $placed['pinned']);

        $menu = [];

        foreach ($placed['menu'] as $at => $item) {
            if ($placed['separator'] === $at) {
                $menu[] = '<atom:menu.separator />';
            }

            $menu[] = $row($item);
        }

        if (($context['hostMenu'] ?? false) && ! isset($context['hostTone'])) {
            array_unshift($menu, '<atom:menu.item>Host item</atom:menu.item>');
        }

        if ($context['hostTone'] ?? false) {
            array_unshift($menu, '<atom:menu.item variant="'.$context['hostTone'].'" confirm>Host '.$context['hostTone'].'</atom:menu.item>');
        }

        $slot = fn (string $name, array $lines) => $lines === [] ? '' : "<x-slot:{$name}>\n".implode("\n", $lines)."\n</x-slot:{$name}>\n";
        $primary = ($context['hostPrimary'] ?? false) ? ['<atom:button variant="primary">Host primary</atom:button>'] : ($placed['primary'] === null ? [] : ['<atom:button variant="primary" wire:click="a'.$placed['primary']['id'].'">Action '.$placed['primary']['id'].'</atom:button>']);

        return "{{-- case {$case} --}}\n".match ($surface) {
            'header' => '<atom:page.header title="T"'.($small ? ' size="sm" level="2"' : '').">\n".$slot('primary', $primary).$slot('actions', $visible).$slot('menu', $menu)."</atom:page.header>\n",
            'toolbar' => "<atom:table.toolbar>\n".$slot('actions', $visible).$slot('menu', $menu)."</atom:table.toolbar>\n",
            'bulk' => "<atom:table>\n".$slot('checked', [...$visible, ...$pinned]).$slot('checked-menu', $menu)."</atom:table>\n",
            'card', 'panel' => "<atom:{$surface} heading=\"T\">\n".$slot('actions', $visible).$slot('menu', $menu)."</atom:{$surface}>\n",
            'row' => "<atom:table.row>\n<atom:table.actions>\n".implode("\n", $menu)."\n</atom:table.actions>\n</atom:table.row>\n",
        };
    };

    /**
     * Every combination: normals 0 to 4, lows 0 to 1, a tone set, primaries 0 to 2
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    $matrix = function (): array {
        $toneSets = [
            'none' => [],
            'warning' => [['warning', null]],
            'danger' => [['danger', null]],
            'delete' => [['delete', null]],
            'warning and delete' => [['warning', null], ['delete', null]],
            'a low delete' => [['delete', 'low']],
            'a low warning' => [['warning', 'low']],
            'a low warning and a danger' => [['warning', 'low'], ['danger', null]],
            'danger and delete' => [['danger', null], ['delete', null]],
        ];
        $cases = [];

        // the slots the actions are written in: the engine reads them in this order, and where one is written never changes where it lands
        $layouts = [
            'actions and menu' => ['normals' => ['actions'], 'lows' => ['menu'], 'toned' => 'actions'],
            'default, end and checked' => ['normals' => ['default', 'end', 'checked'], 'lows' => ['checked-menu', 'menu'], 'toned' => 'end'],
            'toned in the menu slot' => ['normals' => ['actions', 'default'], 'lows' => ['menu'], 'toned' => 'menu'],
        ];

        foreach ($layouts as $layout) {
            foreach (range(0, 4) as $normals) {
                foreach ([0, 1] as $lows) {
                    foreach ($toneSets as $tones) {
                        foreach ([0, 1, 2] as $primaries) {
                            $items = [];
                            $id = 0;

                            for ($i = 0; $i < $primaries; $i++) {
                                $items[] = ['id' => 'p'.$id++, 'slot' => $i === 0 ? 'primary' : 'actions', 'priority' => 'primary', 'tone' => null, 'position' => $id];
                            }

                            for ($i = 0; $i < $normals; $i++) {
                                $items[] = ['id' => 'n'.$id++, 'slot' => $layout['normals'][$i % count($layout['normals'])], 'priority' => null, 'tone' => null, 'position' => $id];
                            }

                            for ($i = 0; $i < $lows; $i++) {
                                $items[] = ['id' => 'l'.$id++, 'slot' => $layout['lows'][$i % count($layout['lows'])], 'priority' => null, 'tone' => null, 'position' => $id];
                            }

                            foreach ($tones as [$tone, $priority]) {
                                // a toned action in the menu slot is `low` by default, so it is never the bulk bar's pin
                                $items[] = ['id' => 't'.$id++, 'slot' => $layout['toned'], 'priority' => $priority, 'tone' => $tone, 'position' => $id];
                            }

                            $cases[] = $items;
                        }
                    }
                }
            }
        }

        return $cases;
    };

    it('finds nothing in what place() returns, for every surface and every case', function (string $surface, array $context) use ($checks, $write, $matrix) {
        $files = [];

        foreach ($matrix() as $case => $items) {
            // a primary slot belongs to the header: on any other surface it is a normal action
            if ($surface !== 'header') {
                $items = array_map(fn ($item) => [...$item, 'slot' => $item['slot'] === 'primary' ? 'actions' : $item['slot'], 'priority' => $item['priority'] === 'primary' ? null : $item['priority']], $items);
            }

            // a host's own toned menu item sits before the engine's rows, so it only agrees with a menu of rows at least as heavy
            if (isset($context['hostTone'])) {
                $menu = ActionPlacement::place($surface, $items, $context)['menu'];
                $weights = ['warning' => 1, 'danger' => 2, 'delete' => 2];

                if (collect($menu)->contains(fn ($row) => ($weights[$row['tone'] ?? ''] ?? 0) < $weights[$context['hostTone']])) {
                    continue;
                }
            }

            $files['resources/views/case-'.$case.'.blade.php'] = $write($surface, $context, $items, $case);
        }

        $dir = DesignHost::temp($files);

        try {
            $result = (new DesignCheck)->scan($dir, $checks);
            $found = DesignHost::found($result);
        } finally {
            DesignHost::remove($dir);
        }

        expect($result['errors'])->toBe([])
            ->and($result['scanned']['blade'])->toBe(count($files))
            ->and($found)->toBe([]);
    })->with([
        'header' => ['header', []],
        'header size sm' => ['header', ['small' => true]],
        'header with a host primary' => ['header', ['hostPrimary' => true]],
        'header with host menu items' => ['header', ['hostMenu' => true]],
        'toolbar' => ['toolbar', []],
        'toolbar with host menu items' => ['toolbar', ['hostMenu' => true]],
        'bulk bar' => ['bulk', []],
        'card' => ['card', []],
        'panel' => ['panel', ['hostMenu' => true]],
        'row' => ['row', []],
        'header with a host warning item' => ['header', ['hostMenu' => true, 'hostTone' => 'warning']],
        'header with a host danger item' => ['header', ['hostMenu' => true, 'hostTone' => 'danger']],
        'bulk bar with a host warning item' => ['bulk', ['hostMenu' => true, 'hostTone' => 'warning']],
    ]);

    it('is not blind: a perturbed output is flagged by the check that owns the rule', function () use ($write) {
        $normals = array_map(fn ($i) => ['id' => 'n'.$i, 'slot' => 'actions', 'priority' => null, 'tone' => null, 'position' => $i], range(0, 3));
        $toned = [...$normals, ['id' => 'w', 'slot' => 'menu', 'priority' => null, 'tone' => 'warning', 'position' => 0]];
        $flagged = fn (string $source, string $id) => DesignHost::scanSource($source, $id) !== [];

        $clean = $write('header', [], $normals, 0);
        $menu = $write('header', [], $toned, 0);
        $warning = '<atom:menu.item variant="warning" confirm wire:click="aw">Action w</atom:menu.item>';
        $bulk = $write('bulk', [], [...$normals, ['id' => 'd', 'slot' => 'actions', 'priority' => null, 'tone' => 'delete', 'position' => 9]], 0);

        // the written output is clean ...
        expect($menu)->toContain($warning)
            ->and($flagged($clean, 'action-cap'))->toBeFalse()
            ->and($flagged($menu, 'menu-order'))->toBeFalse()
            ->and($flagged($menu, 'tone-unconfirmed'))->toBeFalse()
            ->and($flagged($bulk, 'action-cap'))->toBeFalse()
            // ... and one more visible button than the cap, the separator taken out, the warning row first, no confirm, a primary added, are each flagged
            ->and($flagged(str_replace('</x-slot:actions>', '<atom:button>One more</atom:button></x-slot:actions>', $clean), 'action-cap'))->toBeTrue()
            ->and($flagged(str_replace('<atom:menu.separator />', '', $menu), 'menu-order'))->toBeTrue()
            ->and($flagged(str_replace('<x-slot:menu>', "<x-slot:menu>\n".$warning, str_replace($warning, '', $menu)), 'menu-order'))->toBeTrue()
            ->and($flagged(str_replace(' variant="warning" confirm', ' variant="warning"', $menu), 'tone-unconfirmed'))->toBeTrue()
            ->and($flagged(str_replace('<x-slot:actions>', '<x-slot:primary><atom:button variant="primary">P</atom:button></x-slot:primary><x-slot:actions><atom:button variant="primary">Q</atom:button>', $clean), 'extra-primary'))->toBeTrue()
            ->and($flagged(str_replace('<atom:button type="delete"', '<atom:button variant="danger"', $bulk), 'one-red'))->toBeTrue();
    });

    it('is not vacuous: the matrix reaches the primary, the pin, the menu and the separator', function () use ($matrix) {
        $reached = ['primary' => false, 'pinned' => false, 'menu' => false, 'separator' => false, 'overflow' => false];

        foreach ($matrix() as $items) {
            foreach (['header', 'bulk'] as $surface) {
                $placed = ActionPlacement::place($surface, $items);

                $reached['primary'] = $reached['primary'] || $placed['primary'] !== null;
                $reached['pinned'] = $reached['pinned'] || $placed['pinned'] !== [];
                $reached['menu'] = $reached['menu'] || $placed['menu'] !== [];
                $reached['separator'] = $reached['separator'] || $placed['separator'] !== null;
                $reached['overflow'] = $reached['overflow'] || count($placed['visible']) === ActionPlacement::CAPS[$surface]['visible'] && $placed['menu'] !== [];
            }
        }

        expect($reached)->toBe(['primary' => true, 'pinned' => true, 'menu' => true, 'separator' => true, 'overflow' => true]);
    });
});
