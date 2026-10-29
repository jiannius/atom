<?php

use Livewire\Component;
use Livewire\Livewire;

// <atom:table.toolbar>: the `actions` slot (the host's buttons), the older `end` slot (the same place, kept as a silent alias
// through 4.x) and the `menu` slot (the items of the ⋯ overflow menu). End group order: count, actions, end, trashed, ⋯.

/** Render the Blade in a Livewire component, so the `@if` markers a real page prints are compiled in. */
function renderToolbarInLivewire(string $blade): string
{
    $component = new class extends Component {
        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    return Livewire::test($component::class)->html();
}

/** The end group's direct children, as a short description each: the count span, a button by id, the trashed toggle, the ⋯ dropdown. */
function toolbarEndOrder(string $html): array
{
    $end = domQuery($html, '//*[@data-atom-table-toolbar-end]')[0];
    $order = [];

    foreach ($end->childNodes as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $order[] = match (true) {
            $child->hasAttribute('data-atom-table-count') => 'count',
            $child->hasAttribute('id') => $child->getAttribute('id'),
            (new DOMXPath($child->ownerDocument))->query('.//*[@data-atom-table-trashed]', $child)->length > 0 => 'trashed',
            (new DOMXPath($child->ownerDocument))->query('.//*[@data-atom-menu]|.//*[@role="menu"]', $child)->length > 0 => 'menu',
            default => $child->nodeName,
        };
    }

    return $order;
}

$both = '<atom:table.toolbar trashed>x'
    .'<x-slot:menu><atom:menu.item>Archive all</atom:menu.item></x-slot:menu>'
    .'<x-slot:end><button id="e" type="button">End</button></x-slot:end>'
    .'<x-slot:actions><button id="a" type="button">Action</button></x-slot:actions>'
    .'</atom:table.toolbar>';

it('puts the end group in order: count, actions, end, trashed, then the ⋯ menu (whatever order the slots are written in)', function () use ($both) {
    expect(toolbarEndOrder(renderBlade($both)))->toBe(['count', 'a', 'e', 'trashed', 'menu']);
});

it('draws the ⋯ trigger as a ghost icon button named "More actions" with a tooltip, opening the menu of the host\'s items', function () {
    $html = renderBlade('<atom:table.toolbar>x<x-slot:menu><atom:menu.item id="i">Archive all</atom:menu.item></x-slot:menu></atom:table.toolbar>');

    $trigger = domQuery($html, '//*[@data-atom-table-toolbar-end]//*[@data-atom-dropdown]//button[@aria-label="More actions"]');

    expect($trigger)->toHaveCount(1)
        // the tooltip wrapper is around the trigger, with the text as its content
        ->and(domQuery($html, '//*[@data-atom-table-toolbar-end]//*[@data-atom-tooltip]//button[@aria-label="More actions"]'))->toHaveCount(1)
        ->and(trim(domQuery($html, '//*[@data-atom-tooltip-content]')[0]->textContent))->toBe('More actions')
        // the menu holds the host's item
        ->and(domQuery($html, '//*[@role="menu"]//*[@id="i"]'))->toHaveCount(1)
        // and the menu is a popover aligned to the end, as page.header draws it
        ->and(domQuery($html, '//*[@role="menu"][@popover]'))->toHaveCount(1);
});

it('draws no ⋯ button for a toolbar with no menu slot, an empty one, or one of only false @if blocks', function () {
    $none = renderBlade('<atom:table.toolbar>x<x-slot:end><button id="n">E</button></x-slot:end></atom:table.toolbar>');
    $blank = renderBlade('<atom:table.toolbar>x<x-slot:menu>   </x-slot:menu></atom:table.toolbar>');
    $false = renderToolbarInLivewire('<atom:table.toolbar>x<x-slot:menu>@if (false) <atom:menu.item>a</atom:menu.item> @endif @if (false) <atom:menu.item>b</atom:menu.item> @endif</x-slot:menu></atom:table.toolbar>');

    foreach ([$none, $blank, $false] as $html) {
        expect(domQuery($html, '//button[@aria-label="More actions"]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@role="menu"]'))->toBeEmpty();
    }
});

it('draws no end group, and gives the start group the full row, for an actions slot of only false @if blocks', function () {
    $html = renderToolbarInLivewire('<atom:table.toolbar :count="false">x<x-slot:actions>@if (false) <button>a</button> @endif @if (false) <button>b</button> @endif</x-slot:actions></atom:table.toolbar>');

    expect(domQuery($html, '//*[@data-atom-table-toolbar-end]'))->toBeEmpty()
        ->and(domClasses(domQuery($html, '//*[@data-atom-table-toolbar-start]')[0]))->toContain('basis-full');
});

it('prints a real @if block in the menu slot', function () {
    $html = renderToolbarInLivewire('<atom:table.toolbar>x<x-slot:menu>@if (true) <atom:menu.item id="real">Archive</atom:menu.item> @endif</x-slot:menu></atom:table.toolbar>');

    expect(domQuery($html, '//button[@aria-label="More actions"]'))->toHaveCount(1)
        ->and(domQuery($html, '//*[@role="menu"]//*[@id="real"]'))->toHaveCount(1);
});

it('treats each of actions, end and menu alone as an end group, so the start group shares the row on a phone', function (string $slot) {
    $html = renderBlade('<atom:table.toolbar :count="false">x<x-slot:'.$slot.'><button id="n">E</button></x-slot:'.$slot.'></atom:table.toolbar>');

    expect(domQuery($html, '//*[@data-atom-table-toolbar-end]'))->toHaveCount(1)
        ->and(domClasses(domQuery($html, '//*[@data-atom-table-toolbar-start]')[0]))->toContain('basis-48')->not->toContain('basis-full')
        // the end group is shown on a phone, not `max-sm:hidden`
        ->and(domClasses(domQuery($html, '//*[@data-atom-table-toolbar-end]')[0]))->not->toContain('max-sm:hidden');
})->with(['actions', 'end', 'menu']);

it('renders a toolbar with only an end slot as it did before the actions and menu slots existed', function () {
    // captured from the toolbar before `actions` and `menu`, whitespace collapsed; no ⋯ and no extra markup appear
    $before = '<div class="flex min-w-0 grow basis-full flex-wrap items-start gap-2" data-atom-table-toolbar> <div class="flex min-w-0 grow flex-wrap items-start gap-2 sm:basis-0 sm:min-w-48 [&_[data-atom-table-search]]:w-full sm:[&_[data-atom-table-search]]:w-60 basis-48" data-atom-table-toolbar-start> x </div> <div x-data="tableCount" class="ms-auto flex min-h-(--atom-control-h) shrink-0 flex-wrap items-center gap-2 self-start text-xs text-fg-subtle" data-atom-table-toolbar-end> <span x-show="text" x-cloak x-text="text" class="tabular-nums text-xs text-fg-muted max-sm:hidden" data-atom-table-count></span> <button id="n">Export</button> </div> </div>';

    $html = renderBlade('<atom:table.toolbar>x<x-slot:end><button id="n">Export</button></x-slot:end></atom:table.toolbar>');

    expect(trim(preg_replace('/\s+/', ' ', $html)))->toBe($before);
});

it('prints both slots when both are given, the older one after the newer', function () {
    $html = renderBlade('<atom:table.toolbar :count="false">x<x-slot:end><button id="e">E</button></x-slot:end><x-slot:actions><button id="a">A</button></x-slot:actions></atom:table.toolbar>');

    expect(toolbarEndOrder($html))->toBe(['a', 'e']);
});
