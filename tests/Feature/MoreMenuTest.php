<?php

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\Livewire;

// The one ⋯ trigger (<atom:menu._more>) and the table's `checked-menu` slot: the header, the toolbar, a row and the bulk bar all draw
// the same control, and the bar's ⋯ opens last in its group.

/** Render the Blade in a Livewire component, so the `@if` markers a real page prints are compiled in. */
function renderMoreInLivewire(string $blade): string
{
    $component = new class extends Component {
        use WithTable;

        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    return Livewire::test($component::class)->html();
}

/** Markup with the whitespace between tags removed, so two renders can be compared by what they draw. */
function moreFlat(string $html): string
{
    return preg_replace('/\s+/', ' ', trim(preg_replace('/>\s+</', '><', $html)));
}

/** The first ⋯ dropdown inside a fragment, as flat markup. */
function moreDropdown(string $html): string
{
    $node = domQuery($html, '//*[@data-atom-dropdown]')[0];

    return moreFlat($node->ownerDocument->saveHTML($node));
}

const MORE_ITEM = '<atom:menu.item>Edit</atom:menu.item>';

const MORE_ROWS = '<x-slot:rows><atom:table.row><atom:table.cell>x</atom:table.cell></atom:table.row></x-slot:rows>';

describe('the shared trigger', function () {
    it('is a ghost-or-default icon button named "More actions" that opens its menu at the end', function () {
        $html = renderBlade('<atom:menu._more>'.MORE_ITEM.'</atom:menu._more>');
        $button = domQuery($html, '//*[@data-atom-dropdown]/button')[0];

        expect($button->getAttribute('aria-label'))->toBe('More actions')
            ->and($html)->toContain("placement: 'bottom-end'")
            ->and(domQuery($html, '//*[@data-atom-dropdown]//button//circle'))->toHaveCount(3)
            ->and(domQuery($html, '//*[@data-atom-menu][@popover]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//*[@data-atom-menu]')[0]))->toContain('w-[220px]');
    });

    it('takes the surface\'s variant and size, a tooltip name and a narrow menu', function () {
        $ghost = renderBlade('<atom:menu._more variant="ghost" size="sm" :wide="false">'.MORE_ITEM.'</atom:menu._more>');
        $tip = renderBlade('<atom:menu._more variant="ghost" tooltip>'.MORE_ITEM.'</atom:menu._more>');

        expect(domClasses(domQuery($ghost, '//*[@data-atom-dropdown]/button')[0]))->toContain('bg-transparent', 'h-control-sm')
            ->and(domClasses(domQuery($ghost, '//*[@data-atom-menu]')[0]))->not->toContain('w-[220px]')
            ->and(domQuery($tip, '//*[@data-atom-dropdown]//*[@data-atom-tooltip]'))->toHaveCount(1)
            ->and(domQuery($tip, '//*[@data-atom-dropdown]//button[@aria-label="More actions"]'))->toHaveCount(1);
    });

    it('is the trigger of the page header, the toolbar, a row and the bulk bar, drawn once and the same', function () {
        $header = renderBlade('<atom:page.header title="T"><x-slot:menu>'.MORE_ITEM.'</x-slot:menu></atom:page.header>');
        $small = renderBlade('<atom:page.header title="T" size="sm"><x-slot:menu>'.MORE_ITEM.'</x-slot:menu></atom:page.header>');
        $toolbar = renderBlade('<atom:table.toolbar><x-slot:menu>'.MORE_ITEM.'</x-slot:menu></atom:table.toolbar>');
        $row = renderBlade('<atom:table.actions>'.MORE_ITEM.'</atom:table.actions>');
        $bar = renderBlade('<atom:table :empty="false"><x-slot:checked-menu>'.MORE_ITEM.'</x-slot:checked-menu>'.MORE_ROWS.'</atom:table>');

        expect(moreDropdown($header))->toBe(moreDropdown(renderBlade('<atom:menu._more>'.MORE_ITEM.'</atom:menu._more>')))
            ->and(moreDropdown($small))->toBe(moreDropdown(renderBlade('<atom:menu._more size="sm">'.MORE_ITEM.'</atom:menu._more>')))
            ->and(moreDropdown($toolbar))->toBe(moreDropdown(renderBlade('<atom:menu._more variant="ghost" tooltip>'.MORE_ITEM.'</atom:menu._more>')))
            ->and(moreDropdown($row))->toBe(moreDropdown(renderBlade('<atom:menu._more variant="ghost" size="sm" :wide="false">'.MORE_ITEM.'</atom:menu._more>')))
            ->and(moreDropdown($bar))->toBe(moreDropdown($toolbar));
    });

});

describe('the `checked-menu` slot', function () {
    it('draws the ⋯ last in the bar\'s group, after the host\'s buttons, named "More actions"', function () {
        $html = renderBlade('<atom:table :empty="false">
            <x-slot:checked><atom:button id="a" size="sm">Send</atom:button><atom:button id="b" type="delete" size="sm">Delete</atom:button></x-slot:checked>
            <x-slot:checked-menu>'.MORE_ITEM.'</x-slot:checked-menu>'.MORE_ROWS.'
        </atom:table>');

        $group = domQuery($html, '//*[@data-atom-table-checked]/div[last()]')[0];
        $order = collect($group->childNodes)->filter(fn ($n) => $n instanceof DOMElement)->map(fn ($n) => match (true) {
            $n->hasAttribute('data-atom-dropdown') => 'more',
            default => $n->getAttribute('id') ?: $n->nodeName,
        })->values()->all();

        expect($order)->toEqual(['a', 'b', 'more'])
            ->and(domQuery($html, '//*[@data-atom-table-checked]//*[@data-atom-dropdown]//button[@aria-label="More actions"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-table-checked]//*[@data-atom-dropdown]//*[@data-atom-menu]//*[contains(., "Edit")]'))->not->toBeEmpty();
    });

    it('draws a bar for the menu alone, and keeps the slot\'s own attributes on the group', function () {
        $html = renderBlade('<atom:table :empty="false"><x-slot:checked-menu>'.MORE_ITEM.'</x-slot:checked-menu>'.MORE_ROWS.'</atom:table>');
        $accent = renderBlade('<atom:table :empty="false"><x-slot:checked tone="accent" data-x="1"><b>bulk</b></x-slot:checked><x-slot:checked-menu>'.MORE_ITEM.'</x-slot:checked-menu>'.MORE_ROWS.'</atom:table>');

        expect(domQuery($html, '//*[@data-atom-table-checked]//*[@data-atom-dropdown]'))->toHaveCount(1)
            ->and(domQuery($accent, '//*[@data-atom-table-checked][@data-tone="accent"]//*[@data-x="1"]//b'))->toHaveCount(1)
            ->and(domQuery($accent, '//*[@tone]'))->toBeEmpty();
    });

    it('draws nothing for a slot of only false blocks, and no bar if it is the only slot', function () {
        $falses = '@if (false) '.MORE_ITEM.' @endif @if (false) '.MORE_ITEM.' @endif';
        $both = renderMoreInLivewire('<atom:table :empty="false">
            <x-slot:checked><b>bulk</b></x-slot:checked>
            <x-slot:checked-menu>'.$falses.'</x-slot:checked-menu>'.MORE_ROWS.'
        </atom:table>');
        $alone = renderMoreInLivewire('<atom:table :empty="false"><x-slot:checked-menu>'.$falses.'</x-slot:checked-menu>'.MORE_ROWS.'</atom:table>');

        expect(domQuery($both, '//*[@data-atom-table-checked]'))->toHaveCount(1)
            ->and(domQuery($both, '//*[@data-atom-table-checked]//*[@data-atom-dropdown]'))->toBeEmpty()
            ->and(domQuery($alone, '//*[@data-atom-table-checked]'))->toBeEmpty();
    });

    it('keeps a delete item inside the bar, so it counts the selection', function () {
        $html = renderBlade('<atom:table :empty="false"><x-slot:checked-menu><atom:menu.item variant="delete">Delete</atom:menu.item></x-slot:checked-menu>'.MORE_ROWS.'</atom:table>');

        // the item's handler asks `$el.closest('[data-atom-table-checked]')` for the count, so the menu must sit inside the bar
        expect(domQuery($html, '//*[@data-atom-table-checked]//*[@data-atom-menu]//*[contains(., "Delete")]'))->not->toBeEmpty()
            ->and($html)->toContain('Delete :count records?')
            ->and($html)->toContain('data-atom-table-checked');
    });
});
