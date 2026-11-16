<?php

use Jiannius\Atom\Services\ActionItems;
use Livewire\Component;
use Livewire\Livewire;

// <atom:action> in the table's two bars (design rules, phase 5): `table.toolbar` reads its default slot, `actions`, `end` and `menu`; the bulk bar reads
// `checked` and `checked-menu`. What a bar with no action prints is proven byte for byte by ActionGoldenTest; this file proves what the engine adds.

beforeEach(function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
});

/** Render Blade inside a Livewire component with the table trait, so the `@if` markers of a real page are compiled in */
function barLivewire(string $blade): string
{
    $component = new class extends Component {
        use \Jiannius\Atom\Traits\WithTable;

        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    return Livewire::test($component::class)->html();
}

/**
 * What a bar's group draws, child by child: `Label:place` for an action, `menu` for the ⋯ dropdown, the id of a host element, the data-attribute name of a trashed toggle or divider.
 *
 * @return list<string>
 */
function barChildren(string $html, string $group): array
{
    $element = domQuery($html, '//*[@'.$group.']')[0] ?? null;
    $order = [];

    foreach ($element?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $order[] = match (true) {
            $child->hasAttribute('data-atom-action') => actionText($child).':'.$child->getAttribute('data-atom-action'),
            $child->hasAttribute('data-atom-dropdown') => 'menu',
            $child->hasAttribute('data-atom-table-count') => 'count',
            $child->hasAttribute('data-atom-table-toolbar-divider') => 'divider',
            $child->getAttribute('id') !== '' => $child->getAttribute('id'),
            default => $child->nodeName,
        };
    }

    return $order;
}

/**
 * The rows of a bar's ⋯ menu, in order: the text of a row, `|` for a separator.
 *
 * @return list<string>
 */
function barMenu(string $html, string $group): array
{
    $menu = domQuery($html, '//*[@'.$group.']//*[@data-atom-menu]')[0] ?? null;
    $rows = [];

    foreach ($menu?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $rows[] = $child->hasAttribute('data-atom-menu-separator') ? '|' : actionText($child);
    }

    return $rows;
}

const TOOLBAR_END = 'data-atom-table-toolbar-end';
const BULK = 'data-atom-table-checked';

describe('table.toolbar', function () {
    it('reads the default slot, `actions`, `end` and `menu`: the engine\'s buttons come after the host\'s `actions` and before its `end`', function () {
        $html = renderBlade('<atom:table.toolbar trashed>
            <atom:table.search />
            <atom:action icon="add" wire:click="a">Add</atom:action>
            <x-slot:actions><button id="host-actions" type="button">Host</button><atom:action icon="download" wire:click="b">Export</atom:action></x-slot:actions>
            <x-slot:end><button id="host-end" type="button">End</button><atom:action icon="send" wire:click="c">Send</atom:action></x-slot:end>
            <x-slot:menu><atom:menu.item>Host item</atom:menu.item><atom:action priority="low" wire:click="d">Print</atom:action></x-slot:menu>
        </atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toBe(['count', 'host-actions', 'Add:visible', 'Export:visible', 'host-end', 'div', 'menu'])
            ->and(barMenu($html, TOOLBAR_END))->toBe(['Host item', 'Send', 'Print']);
    });

    it('takes an action out of the controls: the start group keeps the search and nothing else', function () {
        $html = renderBlade('<atom:table.toolbar><atom:table.search /><atom:action wire:click="a">Add</atom:action></atom:table.toolbar>');
        $start = domQuery($html, '//*[@data-atom-table-toolbar-start]')[0];

        expect(domQuery($html, '//*[@data-atom-table-toolbar-start]//*[@data-atom-action]'))->toBe([])
            ->and(domQuery($html, '//*[@data-atom-table-toolbar-end]//*[@data-atom-action="visible"]'))->toHaveCount(1)
            ->and($start->getAttribute('class'))->toContain('basis-48');
    });

    it('draws two buttons, the rest in the ⋯ with the warning and the delete after a separator', function () {
        $html = renderBlade('<atom:table.toolbar>
            <atom:action wire:click="a">Alpha</atom:action>
            <atom:action wire:click="b">Bravo</atom:action>
            <atom:action wire:click="c">Charlie</atom:action>
            <atom:action priority="low" wire:click="d">Delta</atom:action>
            <atom:action tone="delete" wire:click="e">Foxtrot</atom:action>
            <atom:action tone="warning" wire:click="f">Echo</atom:action>
        </atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toBe(['count', 'Alpha:visible', 'Bravo:visible', 'menu'])
            ->and(barMenu($html, TOOLBAR_END))->toBe(['Charlie', 'Delta', '|', 'Echo', 'Foxtrot']);
    });

    it('draws a visible action ghost, and one with an icon icon-only below sm with its name in an `sr-only` label', function () {
        $html = renderBlade('<atom:table.toolbar>
            <atom:action icon="add" wire:click="a">Add</atom:action>
            <atom:action wire:click="b">Plain</atom:action>
        </atom:table.toolbar>');

        [$withIcon, $plain] = domQuery($html, '//*[@data-atom-action="visible"]');
        $label = domQuery($html, '//*[@data-atom-action="visible"]//span[contains(@class, "max-sm:sr-only")]');

        expect($label)->toHaveCount(1)
            ->and(actionText($label[0]))->toBe('Add')
            ->and(actionText($withIcon))->toBe('Add')
            ->and(actionText($plain))->toBe('Plain')
            ->and(domClasses($withIcon))->toContain('bg-transparent', 'hover:bg-fg/5')
            ->and(domClasses($plain))->toContain('bg-transparent');
    });

    it('keeps the copy of a confirm when the label is icon-only: the dialog still says what the action is called', function () {
        $html = renderBlade('<atom:table.toolbar>
            <atom:action icon="download" confirm wire:click="a">Export all</atom:action>
            <atom:action icon="send" :confirm="[\'heading\' => \'Send them?\']" wire:click="b">Send</atom:action>
        </atom:table.toolbar>');

        [$first, $second] = domQuery($html, '//*[@data-atom-action="visible"]');

        expect(json_decode($first->getAttribute('data-atom-confirm'), true))->toBe(['heading' => 'Export all?', 'buttonConfirm' => 'Export all'])
            ->and(json_decode($second->getAttribute('data-atom-confirm'), true))->toBe(['heading' => 'Send them?', 'buttonConfirm' => 'Send']);
    });

    it('confirms a warning action in the ⋯ in its tone, with the label\'s copy', function () {
        $html = renderBlade('<atom:table.toolbar><atom:action tone="warning" wire:click="a">Void all</atom:action></atom:table.toolbar>');
        $row = domQuery($html, '//*[@data-atom-menu]//*[@data-atom-action="menu"]')[0];

        expect(json_decode($row->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'warning', 'heading' => 'Void all?', 'buttonConfirm' => 'Void all']);
    });

    it('draws the separator before a toned row that follows only the host\'s own menu items', function () {
        $html = renderBlade('<atom:table.toolbar><x-slot:menu><atom:menu.item>Host</atom:menu.item><atom:action tone="warning" wire:click="a">Void</atom:action></x-slot:menu></atom:table.toolbar>');
        $alone = renderBlade('<atom:table.toolbar><x-slot:menu><atom:action tone="warning" wire:click="a">Void</atom:action></x-slot:menu></atom:table.toolbar>');

        expect(barMenu($html, TOOLBAR_END))->toBe(['Host', '|', 'Void'])
            ->and(barMenu($alone, TOOLBAR_END))->toBe(['Void']);
    });

    it('draws no divider-less group and no ⋯ of its own for a menu that only holds the host\'s items', function () {
        $html = renderBlade('<atom:table.toolbar><x-slot:menu><atom:menu.item>Only</atom:menu.item></x-slot:menu></atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toBe(['count', 'menu'])
            ->and(barMenu($html, TOOLBAR_END))->toBe(['Only']);
    });

    it('puts the column picker\'s divider after an action: the end group counts the engine\'s buttons', function () {
        $html = renderBlade('<atom:table.toolbar :count="false" columns><atom:action wire:click="a">Add</atom:action></atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toContain('Add:visible', 'divider');
    });

    it('draws the end group for an action alone, and none for a toolbar with nothing in it but its count', function () {
        $html = renderBlade('<atom:table.toolbar :count="false"><atom:action wire:click="a">Add</atom:action></atom:table.toolbar>');
        $none = renderBlade('<atom:table.toolbar :count="false"><atom:table.search /></atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toBe(['Add:visible'])
            ->and($none)->not->toContain(TOOLBAR_END);
    });

    it('draws each action once, with the host\'s attributes and no marker', function () {
        $html = renderBlade('<atom:table.toolbar><atom:action wire:click="go" data-testid="go" icon="add">Add</atom:action><x-slot:menu><atom:action wire:click="more" data-testid="more">More</atom:action></x-slot:menu></atom:table.toolbar>');

        expect(substr_count($html, 'wire:click="go"'))->toBe(1)
            ->and(substr_count($html, 'wire:click="more"'))->toBe(1)
            ->and(substr_count($html, 'data-testid="go"'))->toBe(1)
            ->and($html)->not->toContain('atom:action:');
    });

    it('lets Blade control flow decide: an `@if` that is false places nothing and draws no group', function () {
        $html = barLivewire('<atom:table.toolbar :count="false"><x-slot:actions>@if (false) <atom:action wire:click="a">Add</atom:action> @endif @if (true) <atom:action wire:click="b">Yes</atom:action> @endif</x-slot:actions></atom:table.toolbar>');
        $none = barLivewire('<atom:table.toolbar :count="false"><x-slot:actions>@if (false) <atom:action wire:click="a">Add</atom:action> @endif</x-slot:actions></atom:table.toolbar>');

        expect(barChildren($html, TOOLBAR_END))->toBe(['Yes:visible'])
            ->and($none)->not->toContain(TOOLBAR_END);
    });

    it('registers nothing that is left: the registry is empty after the toolbar places', function () {
        renderBlade('<atom:table.toolbar><atom:action wire:click="a">Add</atom:action><x-slot:menu><atom:action wire:click="b">B</atom:action></x-slot:menu></atom:table.toolbar>');

        expect(app(ActionItems::class)->pending())->toBe(0);
    });
});

/** the bar's inner group: the host's markup, the visible buttons, the pin, the ⋯ */
function bulkGroup(string $html): array
{
    $bar = domQuery($html, '//*[@data-atom-table-checked]/div[last()]')[0] ?? null;
    $order = [];

    foreach ($bar?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $order[] = match (true) {
            $child->hasAttribute('data-atom-action') => actionText($child).':'.$child->getAttribute('data-atom-action'),
            $child->hasAttribute('data-atom-dropdown') => 'menu',
            $child->getAttribute('id') !== '' => $child->getAttribute('id'),
            default => $child->nodeName,
        };
    }

    return $order;
}

function bulkTable(string $checked, string $menu = ''): string
{
    return '<atom:table :empty="false">'
        .($checked !== '' ? '<x-slot:checked>'.$checked.'</x-slot:checked>' : '')
        .($menu !== '' ? '<x-slot:checked-menu>'.$menu.'</x-slot:checked-menu>' : '')
        .'<x-slot:rows></x-slot:rows></atom:table>';
}

describe('the bulk bar', function () {
    it('draws three buttons, the delete pinned last outside the three, and the ⋯ after them', function () {
        $html = renderBlade(bulkTable('
            <atom:action wire:click="a">Alpha</atom:action>
            <atom:action wire:click="b">Bravo</atom:action>
            <atom:action wire:click="c">Charlie</atom:action>
            <atom:action wire:click="d">Delta</atom:action>
            <atom:action tone="delete" wire:click="e">Delete</atom:action>'));

        expect(bulkGroup($html))->toBe(['Alpha:visible', 'Bravo:visible', 'Charlie:visible', 'Delete:pinned', 'menu'])
            ->and(barMenu($html, BULK))->toBe(['Delta']);
    });

    it('pins the first danger or delete action that is not low, and sends a second one to the ⋯', function () {
        $html = renderBlade(bulkTable('
            <atom:action tone="danger" priority="low" wire:click="a">Purge</atom:action>
            <atom:action tone="danger" wire:click="b">Remove</atom:action>
            <atom:action tone="delete" wire:click="c">Delete</atom:action>'));

        expect(bulkGroup($html))->toBe(['Remove:pinned', 'menu'])
            ->and(barMenu($html, BULK))->toBe(['Purge', 'Delete']);
    });

    it('puts a warning action in the ⋯ after a separator, never in the buttons', function () {
        $html = renderBlade(bulkTable('
            <atom:action wire:click="a">Alpha</atom:action>
            <atom:action tone="warning" wire:click="w">Void</atom:action>'));

        expect(bulkGroup($html))->toBe(['Alpha:visible', 'menu'])
            ->and(barMenu($html, BULK))->toBe(['Void']);
    });

    it('reads `checked-menu` as low: an untoned action there goes to the ⋯ even with room for it, and a toned one after a separator', function () {
        $html = renderBlade(bulkTable('', '
            <atom:menu.item>Host row</atom:menu.item>
            <atom:action wire:click="a">Export</atom:action>
            <atom:action tone="warning" wire:click="w">Void</atom:action>
            <atom:action tone="delete" wire:click="d">Delete</atom:action>'));

        expect(bulkGroup($html))->toBe(['menu'])
            ->and(barMenu($html, BULK))->toBe(['Host row', 'Export', '|', 'Void', 'Delete']);
    });

    it('draws the separator before a toned row that follows only the host\'s own `checked-menu` items', function () {
        $html = renderBlade(bulkTable('', '<atom:menu.item>Host</atom:menu.item><atom:action tone="warning" wire:click="a">Void</atom:action>'));

        expect(barMenu($html, BULK))->toBe(['Host', '|', 'Void']);
    });

    it('draws the bar for a slot that holds only actions, and for a `checked-menu` that holds only actions', function () {
        $checked = renderBlade(bulkTable('<atom:action wire:click="a">Alpha</atom:action>'));
        $menu = renderBlade(bulkTable('', '<atom:action wire:click="a">Alpha</atom:action>'));

        expect($checked)->toContain(BULK)
            ->and(bulkGroup($checked))->toBe(['Alpha:visible'])
            ->and($menu)->toContain(BULK)
            ->and(bulkGroup($menu))->toBe(['menu']);
    });

    it('draws no bar when the only markers in the slot were another component\'s', function () {
        // a marker no surface of this owner takes is stripped, so a slot that held nothing else is empty and the bar is not drawn
        $foreign = app(ActionItems::class)->add(['label' => 'Alien', 'html' => 'Alien', 'attributes' => new \Illuminate\View\ComponentAttributeBag]);
        $registry = app(ActionItems::class);
        $reflection = new ReflectionProperty($registry, 'items');
        $items = $reflection->getValue($registry);
        preg_match(ActionItems::PATTERN, $foreign, $found);
        $items[$found[1]]['owner'] = ['someone-else', 99];
        $reflection->setValue($registry, $items);

        $html = renderBlade(bulkTable($foreign));

        expect($html)->not->toContain(BULK)
            ->and($html)->not->toContain('atom:action:');
    });

    it('counts the selection in a pinned delete: the dialog and the button are the delete\'s', function () {
        $html = renderBlade(bulkTable('<atom:action tone="delete" wire:click="e">Delete</atom:action>'));
        $pinned = domQuery($html, '//*[@data-atom-action="pinned"]')[0];

        expect($pinned->getAttribute('data-atom-confirm'))->not->toBe('')
            ->and(domQuery($html, '//*[@data-atom-table-checked]//*[@data-atom-action="pinned"]'))->toHaveCount(1);
    });

    it('draws a pinned danger as the danger tint (inverted)', function () {
        $html = renderBlade(bulkTable('<atom:action tone="danger" wire:click="e">Remove</atom:action>'));
        $pinned = domQuery($html, '//*[@data-atom-action="pinned"]')[0];

        expect(domClasses($pinned))->toContain('bg-danger-bg', 'text-danger-fg');
    });

    it('keeps the host\'s markup first and tints the bar from the slot\'s own `tone`, with actions in it', function () {
        $html = renderBlade('<atom:table :empty="false"><x-slot:checked tone="accent" class="gap-6"><button id="host" type="button">Host</button><atom:action wire:click="a">Alpha</atom:action></x-slot:checked><x-slot:rows></x-slot:rows></atom:table>');
        $bar = domQuery($html, '//*[@data-atom-table-checked]')[0];

        expect(bulkGroup($html))->toBe(['host', 'Alpha:visible'])
            ->and($bar->getAttribute('data-tone'))->toBe('accent')
            ->and($html)->toContain('gap-6');
    });

    it('draws each action once and leaves no marker', function () {
        $html = renderBlade(bulkTable('<atom:action wire:click="go" data-testid="go">Alpha</atom:action>', '<atom:action wire:click="more" data-testid="more">More</atom:action>'));

        expect(substr_count($html, 'wire:click="go"'))->toBe(1)
            ->and(substr_count($html, 'wire:click="more"'))->toBe(1)
            ->and($html)->not->toContain('atom:action:')
            ->and(app(ActionItems::class)->pending())->toBe(0);
    });

    it('keeps the old rule for a slot with no action: blocks that all come out false still draw the bar', function () {
        $html = barLivewire(bulkTable('@if (false) <atom:button>x</atom:button> @endif'));

        expect($html)->toContain(BULK);
    });

    it('lets an `@if` around an action decide, and the bar follows the selection\'s actions', function () {
        $with = barLivewire(bulkTable('@if (true) <atom:action wire:click="a">Alpha</atom:action> @endif'));
        $without = barLivewire(bulkTable('@if (false) <atom:action wire:click="a">Alpha</atom:action> @endif'));

        expect(bulkGroup($with))->toBe(['Alpha:visible'])
            ->and(domQuery($without, '//*[@data-atom-action]'))->toBe([]);
    });
});
