<?php

// The column picker, the server half (the browser half is tests/e2e/table-column-picker.spec.js): the root is wire:ignore, the trigger is
// an icon-only button named "Columns", the menu is labelled, and <atom:table.toolbar columns> draws the picker last in the end group,
// after a divider that only appears when something visible precedes it.

/** The toolbar's end group children, as short names: the count, a host button by id, the trashed toggle, the ⋯ dropdown, the divider, the picker */
function pickerEndOrder(string $html): array
{
    $end = domQuery($html, '//*[@data-atom-table-toolbar-end]')[0];
    $order = [];

    foreach ($end->childNodes as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $has = fn (string $xpath) => (new DOMXPath($child->ownerDocument))->query($xpath, $child)->length > 0;

        $order[] = match (true) {
            $child->hasAttribute('data-atom-table-count') => 'count',
            $child->hasAttribute('data-atom-table-toolbar-divider') => 'divider',
            $child->hasAttribute('data-atom-column-picker') => 'picker',
            $child->hasAttribute('id') => $child->getAttribute('id'),
            $has('.//*[@data-atom-table-trashed]') => 'trashed',
            $has('.//*[@data-atom-menu]') => 'menu',
            default => $child->nodeName,
        };
    }

    return $order;
}

it('draws the picker as a wire:ignore root whose trigger is an icon-only ghost button named "Columns" with a tooltip', function () {
    $html = renderBlade('<atom:table.columns />');
    $root = domQuery($html, '//*[@data-atom-column-picker]')[0];
    $trigger = domQuery($html, '//*[@data-atom-column-picker-trigger]')[0];

    expect($root->hasAttribute('wire:ignore'))->toBeTrue()
        ->and($root->getAttribute('x-data'))->toBe('tableColumns')
        ->and($trigger->getAttribute('aria-label'))->toBe('Columns')
        ->and(domQuery($html, '//*[@data-atom-column-picker-trigger]//*[@data-atom-button-label]'))->toHaveCount(0)
        ->and(domQuery($html, '//*[@data-atom-tooltip]//*[@data-atom-column-picker-trigger]'))->toHaveCount(1)
        ->and(trim(domQuery($html, '//*[@data-atom-tooltip-content]')[0]->textContent))->toBe('Columns');
});

it('labels the menu "Show columns", with the check, lock and reset strings through t()', function () {
    $html = renderBlade('<atom:table.columns />');
    $menu = domQuery($html, '//*[@data-atom-column-menu]')[0];

    expect($menu->getAttribute('aria-label'))->toBe('Show columns')
        ->and($menu->getAttribute('role'))->toBe('menu')
        ->and($menu->getAttribute('popover'))->not->toBeNull()
        ->and(trim(domQuery($html, '//*[@data-atom-column-reset]')[0]->textContent))->toBe('Reset to default')
        ->and($html)->toContain('aria-label="Always shown"')
        ->and(domQuery($html, '//*[@data-hidden-template]')[0]->getAttribute('data-hidden-template'))->toBe(':count hidden');
});

it('prints the item as a menuitemcheckbox template that reads its label with x-text, never as markup', function () {
    $html = renderBlade('<atom:table.columns />');
    $item = domQuery($html, '//template[@x-for]//*[@data-atom-menu-item]')[0];

    expect($item->getAttribute('role'))->toBe('menuitemcheckbox')
        ->and($html)->toContain('x-text="row.label"')
        ->and($html)->not->toContain('x-html')
        ->and($html)->not->toContain('{!!');
});

it('announces the count as the trigger\'s description, not its name', function () {
    $html = renderBlade('<atom:table.columns />');
    $badge = domQuery($html, '//*[@data-atom-column-picker-badge]')[0];
    $description = domQuery($html, '//*[@data-atom-column-picker-description]')[0];

    expect($badge->getAttribute('aria-hidden'))->toBe('true')
        ->and($description->hasAttribute('hidden'))->toBeTrue()
        ->and($description->getAttribute('x-bind:id'))->toBe('descriptionId')
        ->and(domQuery($html, '//*[@data-atom-column-picker-trigger]')[0]->getAttribute('aria-label'))->toBe('Columns');
});

it('takes the table to steer from `for`, printed as a data attribute and not into x-data', function () {
    $html = renderBlade('<atom:table.columns for="contacts" />');
    $root = domQuery($html, '//*[@data-atom-column-picker]')[0];

    expect($root->getAttribute('data-atom-column-picker-for'))->toBe('contacts')
        ->and($root->getAttribute('x-data'))->toBe('tableColumns')
        ->and(domQuery(renderBlade('<atom:table.columns />'), '//*[@data-atom-column-picker-for]'))->toHaveCount(0);
});

it('escapes a hostile `for` inside its attribute', function () {
    $html = renderBlade('<atom:table.columns :for="$for" />', ['for' => '"><script>alert(1)</script>']);

    expect(domQuery($html, '//script'))->toHaveCount(0)
        ->and(domQuery($html, '//*[@data-atom-column-picker]')[0]->getAttribute('data-atom-column-picker-for'))->toBe('"><script>alert(1)</script>');
});

it('draws no picker unless the toolbar has `columns`', function () {
    expect(renderBlade('<atom:table.toolbar>x</atom:table.toolbar>'))->not->toContain('data-atom-column-picker')
        ->and(renderBlade('<atom:table.toolbar :columns="false">x</atom:table.toolbar>'))->not->toContain('data-atom-column-picker')
        ->and(renderBlade('<atom:table.toolbar columns>x</atom:table.toolbar>'))->toContain('data-atom-column-picker');
});

it('puts the divider and the picker last, after count, actions, end, trashed and the ⋯ menu', function () {
    $html = renderBlade('<atom:table.toolbar trashed columns>x'
        .'<x-slot:menu><atom:menu.item>Archive all</atom:menu.item></x-slot:menu>'
        .'<x-slot:end><button id="e" type="button">End</button></x-slot:end>'
        .'<x-slot:actions><button id="a" type="button">Action</button></x-slot:actions>'
        .'</atom:table.toolbar>');

    expect(pickerEndOrder($html))->toBe(['count', 'a', 'e', 'trashed', 'menu', 'divider', 'picker']);
});

it('draws the divider only when something visible precedes the picker', function () {
    // nothing else: no divider (and the count is off)
    expect(pickerEndOrder(renderBlade('<atom:table.toolbar columns :count="false">x</atom:table.toolbar>')))->toBe(['picker']);

    // a host button, the trashed toggle or the ⋯ menu each make one
    expect(pickerEndOrder(renderBlade('<atom:table.toolbar columns :count="false">x<x-slot:actions><button id="a" type="button">A</button></x-slot:actions></atom:table.toolbar>')))->toBe(['a', 'divider', 'picker'])
        ->and(pickerEndOrder(renderBlade('<atom:table.toolbar columns :count="false" trashed>x</atom:table.toolbar>')))->toBe(['trashed', 'divider', 'picker'])
        ->and(pickerEndOrder(renderBlade('<atom:table.toolbar columns :count="false">x<x-slot:menu><atom:menu.item>M</atom:menu.item></x-slot:menu></atom:table.toolbar>')))->toBe(['menu', 'divider', 'picker']);
});

it('ties the divider to the row count when the count alone precedes the picker: no text, no divider, none on a phone', function () {
    $divider = domQuery(renderBlade('<atom:table.toolbar columns>x</atom:table.toolbar>'), '//*[@data-atom-table-toolbar-divider]')[0];
    $withButton = domQuery(renderBlade('<atom:table.toolbar columns>x<x-slot:actions><button type="button">A</button></x-slot:actions></atom:table.toolbar>'), '//*[@data-atom-table-toolbar-divider]')[0];

    expect($divider->getAttribute('x-show'))->toBe('text')
        ->and(domClasses($divider))->toContain('max-sm:hidden')
        ->and($divider->getAttribute('aria-hidden'))->toBe('true')
        ->and($withButton->hasAttribute('x-show'))->toBeFalse()
        ->and(domClasses($withButton))->not->toContain('max-sm:hidden');
});

it('counts the picker as an end group, so the end group is not hidden on a phone and the search keeps its floor', function () {
    $html = renderBlade('<atom:table.toolbar columns>x</atom:table.toolbar>');
    $end = domQuery($html, '//*[@data-atom-table-toolbar-end]')[0];
    $start = domQuery($html, '//*[@data-atom-table-toolbar-start]')[0];

    expect(domClasses($end))->not->toContain('max-sm:hidden')
        ->and($end->hasAttribute('x-show'))->toBeFalse()
        ->and(domClasses($start))->toContain('basis-48');
});

it('passes a string `columns` on as the picker\'s `for`', function () {
    $html = renderBlade('<atom:table.toolbar columns="people-table">x</atom:table.toolbar>');

    expect(domQuery($html, '//*[@data-atom-column-picker]')[0]->getAttribute('data-atom-column-picker-for'))->toBe('people-table');
});
