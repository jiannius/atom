<?php

use Illuminate\Support\Facades\View;

// <atom:action> inside <atom:table.actions>: a row holds no visible action (ActionPlacement::CAPS['row']), so every action is a row of the ⋯ menu:
// untoned first, then the low ones, a separator, then warning, then danger and delete. What the cell prints with no action is proven by ActionGoldenTest.

beforeEach(function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
});

/**
 * The row ⋯ menu's children, in order: the action's text for a row, `|` for a separator.
 *
 * @return list<string>
 */
function tableRowActionsMenu(string $html): array
{
    $menu = domQuery($html, '//*[@data-atom-table-actions]//*[@data-atom-menu]')[0] ?? null;
    $rows = [];

    foreach ($menu?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $rows[] = $child->hasAttribute('data-atom-menu-separator') ? '|' : actionText($child);
    }

    return $rows;
}

it('puts every action in the ⋯ menu, none as a button: untoned, the low ones, a separator, warning, then delete', function () {
    $html = renderBlade('<table><tr><atom:table.actions>
        <atom:action tone="delete" wire:click="destroy(7)">Delete</atom:action>
        <atom:action tone="warning" wire:click="void">Void</atom:action>
        <atom:action priority="low" wire:click="copy">Copy link</atom:action>
        <atom:action icon="edit" href="/edit" wire:navigate>Edit</atom:action>
        <atom:action wire:click="send">Send</atom:action>
    </atom:table.actions></tr></table>');

    expect(tableRowActionsMenu($html))->toBe(['Edit', 'Send', 'Copy link', '|', 'Void', 'Delete'])
        ->and(domQuery($html, '//*[@data-atom-table-actions]//*[@data-atom-action]'))->toHaveCount(5)
        ->and(domQuery($html, '//*[@data-atom-table-actions]//*[@data-atom-action!="menu"]'))->toBeEmpty()
        ->and(domQuery($html, '//*[@data-atom-table-actions]/*[@data-atom-dropdown]'))->toHaveCount(1)
        ->and($html)->not->toContain('atom:action:');
});

it('draws no separator when only toned rows are there, and none before them when nothing untoned precedes them', function () {
    $toned = renderBlade('<table><tr><atom:table.actions>
        <atom:action tone="warning" wire:click="void">Void</atom:action><atom:action tone="delete" wire:click="destroy">Delete</atom:action>
    </atom:table.actions></tr></table>');
    $plain = renderBlade('<table><tr><atom:table.actions><atom:action wire:click="a">Alpha</atom:action><atom:action priority="low" wire:click="b">Bravo</atom:action></atom:table.actions></tr></table>');

    expect(tableRowActionsMenu($toned))->toBe(['Void', 'Delete'])
        ->and(tableRowActionsMenu($plain))->toBe(['Alpha', 'Bravo']);
});

it('prints the host\'s own menu items first, and the actions after them, with a separator before a toned one', function () {
    $html = renderBlade('<table><tr><atom:table.actions>
        <atom:menu.item id="own">Host item</atom:menu.item>
        <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
    </atom:table.actions></tr></table>');

    expect(tableRowActionsMenu($html))->toBe(['Host item', '|', 'Delete']);
});

it('keeps the host\'s own markup as it was when no action is in the slot', function () {
    $html = renderBlade('<table><tr><atom:table.actions><atom:menu.item>Edit</atom:menu.item><atom:menu.separator /><atom:menu.item variant="delete">Delete</atom:menu.item></atom:table.actions></tr></table>');

    expect(tableRowActionsMenu($html))->toBe(['Edit', '|', 'Delete'])
        ->and(domQuery($html, '//*[@data-atom-action]'))->toBeEmpty();
});

describe('wiring', function () {
    it('wires a delete action to the confirm dialog in danger, with the delete copy, and keeps its own wire:click once', function () {
        $html = renderBlade('<table><tr><atom:table.actions><atom:action tone="delete" wire:click="destroy(7)">Delete</atom:action></atom:table.actions></tr></table>');
        $row = domQuery($html, '//*[@data-atom-action="menu"]')[0];
        $confirm = json_decode($row->getAttribute('data-atom-confirm'), true);

        expect($confirm)->toMatchArray(['variant' => 'danger', 'heading' => 'Delete this record?', 'buttonConfirm' => 'Delete'])
            ->and($row->getAttribute('wire:click'))->toBe('destroy(7)')
            ->and(domClasses($row))->toContain('text-danger-fg')
            ->and(substr_count($html, 'wire:click="destroy(7)"'))->toBe(1);
    });

    it('with no action of its own, a delete action calls $wire.delete() once the dialog accepts', function () {
        $html = renderBlade('<table><tr><atom:table.actions><atom:action tone="delete">Delete</atom:action></atom:table.actions></tr></table>');
        $row = domQuery($html, '//*[@data-atom-action="menu"]')[0];

        expect($row->getAttribute('x-on:confirmed'))->toBe('$wire.delete()');
    });

    it('confirms a warning action in warning, and an untoned one not at all', function () {
        $html = renderBlade('<table><tr><atom:table.actions><atom:action tone="warning" wire:click="void">Void</atom:action><atom:action wire:click="send">Send</atom:action></atom:table.actions></tr></table>');
        [$void, $send] = [
            domQuery($html, '//*[@data-atom-action="menu"][contains(., "Void")]')[0],
            domQuery($html, '//*[@data-atom-action="menu"][contains(., "Send")]')[0],
        ];

        expect(json_decode($void->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'warning'])
            ->and($send->hasAttribute('data-atom-confirm'))->toBeFalse();
    });

    it('gives a link action its href through safe_url(), and an unsafe one no link', function () {
        $html = renderBlade('<table><tr><atom:table.actions><atom:action href="/edit">Edit</atom:action><atom:action href="javascript:alert(1)">Bad</atom:action></atom:table.actions></tr></table>');

        expect(domQuery($html, '//a[@role="menuitem"]'))->toHaveCount(1)
            ->and($html)->not->toContain('javascript:');
    });
});

/**
 * Render `$rows` table rows whose ⋯ menus hold three actions each, counting the views the engine renders per action.
 *
 * @return array{html: string, counts: array<string, int>}
 */
function renderRows(int $rows, string $actions): array
{
    $counts = ['action' => 0, 'item' => 0, 'list' => 0, 'menu-item' => 0, 'button' => 0, 'link' => 0];

    View::composer('*', function ($view) use (&$counts) {
        foreach (['action.index' => 'action', 'action._item' => 'item', 'action._list' => 'list', 'menu.item' => 'menu-item', 'button.index' => 'button', 'link' => 'link'] as $suffix => $key) {
            if (str_ends_with($view->name(), '::'.$suffix)) {
                $counts[$key]++;
            }
        }
    });

    $html = renderBlade('<table>@foreach (range(1, $rows) as $n)<tr><td>Row {{ $n }}</td><atom:table.actions>'.$actions.'</atom:table.actions></tr>@endforeach</table>', ['rows' => $rows]);

    return ['html' => $html, 'counts' => $counts];
}

const ROW_ACTIONS = '<atom:action icon="edit" wire:click="edit({{ $n }})">Edit</atom:action>
    <atom:action priority="low" wire:click="copy({{ $n }})">Copy link</atom:action>
    <atom:action tone="delete" wire:click="destroy({{ $n }})">Delete</atom:action>';


describe('render cost', function () {
    it('renders one <atom:action>, one action._item and one menu.item per action for a 200-row table, and no button', function () {
        ['html' => $html, 'counts' => $counts] = renderRows(200, ROW_ACTIONS);

        expect($counts['action'])->toBe(600)
            ->and($counts['item'])->toBe(600)
            ->and($counts['menu-item'])->toBe(600)
            ->and($counts['list'])->toBe(200)
            ->and($counts['button'])->toBe(200)   // the row's own ⋯ trigger, nothing else
            ->and($counts['link'])->toBe(0)
            ->and(domQuery($html, '//*[@data-atom-action]'))->toHaveCount(600)
            ->and(substr_count($html, 'wire:click="destroy('))->toBe(200);
    });

    it('renders nothing of the engine for a row with only menu items of the host\'s own', function () {
        ['counts' => $counts] = renderRows(20, '<atom:menu.item wire:click="edit({{ $n }})">Edit</atom:menu.item>');

        expect($counts['action'])->toBe(0)
            ->and($counts['item'])->toBe(0)
            ->and($counts['menu-item'])->toBe(20);
    });
});
