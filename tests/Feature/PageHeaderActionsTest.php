<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Services\ActionItems;
use Livewire\Component;
use Livewire\Livewire;

// <atom:action> inside <atom:page.header>: the header collects its `primary`, default, `actions` and `menu` slots, places the actions
// (Services\ActionPlacement) and draws each once. What the engine adds to a header that has no action is proven by ActionGoldenTest.

beforeEach(function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');
});

/** Render Blade inside a Livewire component, so the markers of an `@if` or `@foreach` are compiled in */
function actionLivewire(string $blade, array $data = []): string
{
    $component = new class extends Component {
        public static string $blade = '';

        public static array $data = [];

        public function render()
        {
            return \Illuminate\Support\Facades\Blade::render('<div>'.static::$blade.'</div>', static::$data);
        }
    };

    $component::$blade = $blade;
    $component::$data = $data;

    return Livewire::test($component::class)->html();
}

/**
 * What the header's action group draws, child by child: `Label:place` for an action, `menu` for the ⋯ dropdown.
 *
 * @return list<string>
 */
function headerActions(string $html): array
{
    $group = domQuery($html, '//*[@data-atom-page-actions]')[0] ?? null;
    $order = [];

    foreach ($group?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $order[] = match (true) {
            $child->hasAttribute('data-atom-action') => actionText($child).':'.$child->getAttribute('data-atom-action'),
            $child->hasAttribute('data-atom-dropdown') => 'menu',
            default => $child->getAttribute('id') ?: $child->nodeName,
        };
    }

    return $order;
}

/**
 * The ⋯ menu's children, in order: `Label:menuitem-variant` for a row, `|` for a separator.
 *
 * @return list<string>
 */
function headerMenu(string $html): array
{
    $menu = domQuery($html, '//*[@data-atom-page-actions]//*[@data-atom-menu]')[0] ?? null;
    $rows = [];

    foreach ($menu?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $rows[] = match (true) {
            $child->hasAttribute('data-atom-menu-separator') => '|',
            default => actionText($child),
        };
    }

    return $rows;
}

const HEADER_FIVE = '<atom:action wire:click="a">Alpha</atom:action>
    <atom:action wire:click="b">Bravo</atom:action>
    <atom:action wire:click="c">Charlie</atom:action>
    <atom:action priority="low" wire:click="d">Delta</atom:action>
    <atom:action tone="warning" wire:click="e">Echo</atom:action>
    <atom:action tone="delete" wire:click="f">Foxtrot</atom:action>';

describe('what shows', function () {
    it('draws two buttons, the rest in the ⋯ menu, and the primary last', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>
            <atom:action priority="primary" wire:click="send">Send</atom:action>'.HEADER_FIVE.'
        </x-slot:actions></atom:page.header>');

        expect(headerActions($html))->toBe(['Alpha:visible', 'Bravo:visible', 'menu', 'Send:primary'])
            ->and(headerMenu($html))->toBe(['Charlie', 'Delta', '|', 'Echo', 'Foxtrot']);
    });

    it('draws one button in a size="sm" header, none of them the primary, and sizes the buttons sm', function () {
        $html = renderBlade('<atom:page.header title="T" size="sm" level="2"><x-slot:actions>
            <atom:action priority="primary" wire:click="send">Send</atom:action>'.HEADER_FIVE.'
        </x-slot:actions></atom:page.header>');

        expect(headerActions($html))->toBe(['Send:visible', 'menu'])
            ->and(headerMenu($html))->toBe(['Alpha', 'Bravo', 'Charlie', 'Delta', '|', 'Echo', 'Foxtrot'])
            ->and(domClasses(domQuery($html, '//*[@data-atom-action="visible"]')[0]))->toContain('h-control-sm');
    });

    it('never draws a toned action as a button', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>
            <atom:action tone="warning" wire:click="a">Archive</atom:action>
            <atom:action tone="danger" wire:click="b">Purge</atom:action>
            <atom:action tone="delete" wire:click="c">Delete</atom:action>
        </x-slot:actions></atom:page.header>');

        expect(headerActions($html))->toBe(['menu'])
            ->and(headerMenu($html))->toBe(['Archive', 'Purge', 'Delete'])
            ->and(domQuery($html, '//*[@data-atom-page-actions]//*[@data-atom-menu]/*[@role="separator"]'))->toBeEmpty();
    });

    it('lets a toned action be the primary, drawn as the primary and confirming in its tone', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:primary><atom:action priority="primary" tone="danger" wire:click="x">Wipe</atom:action></x-slot:primary></atom:page.header>');
        $primary = domQuery($html, '//*[@data-atom-action="primary"]')[0];

        expect(domClasses($primary))->toContain('bg-accent')
            ->and(json_decode($primary->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'danger', 'heading' => 'Wipe?']);
    });

    it('takes the primary slot\'s default and a menu slot\'s low default, so a tag rename keeps today\'s places', function () {
        $html = renderBlade('<atom:page.header title="T">
            <x-slot:actions><atom:action wire:click="a">Alpha</atom:action></x-slot:actions>
            <x-slot:menu><atom:action wire:click="m">Dup</atom:action><atom:action tone="delete" wire:click="d">Delete</atom:action></x-slot:menu>
            <x-slot:primary><atom:action wire:click="p">Send</atom:action></x-slot:primary>
        </atom:page.header>');

        expect(headerActions($html))->toBe(['Alpha:visible', 'menu', 'Send:primary'])
            ->and(headerMenu($html))->toBe(['Dup', '|', 'Delete']);
    });

    it('reads the default slot too, and still never prints its other content', function () {
        $html = renderBlade('<atom:page.header title="T"><atom:action wire:click="a">Alpha</atom:action><p id="never">never</p></atom:page.header>');

        expect(headerActions($html))->toBe(['Alpha:visible'])
            ->and($html)->not->toContain('id="never"');
    });

    it('leaves a host\'s own primary slot alone, and demotes a primary action beside it', function () {
        $html = renderBlade('<atom:page.header title="T">
            <x-slot:actions><atom:action priority="primary" wire:click="s">Send</atom:action></x-slot:actions>
            <x-slot:primary><atom:button variant="primary" id="own">Own</atom:button></x-slot:primary>
        </atom:page.header>');

        expect(headerActions($html))->toBe(['Send:visible', 'own']);
    });
});

describe('mixing the host\'s markup with actions', function () {
    it('prints the host\'s own markup first in each place, and the actions after it', function () {
        $html = renderBlade('<atom:page.header title="T">
            <x-slot:actions><atom:button id="own1">Own</atom:button><atom:action wire:click="a">Alpha</atom:action></x-slot:actions>
            <x-slot:menu><atom:menu.item id="m1">Host item</atom:menu.item><atom:action tone="warning" wire:click="w">Echo</atom:action></x-slot:menu>
        </atom:page.header>');

        expect(headerActions($html))->toBe(['own1', 'Alpha:visible', 'menu'])
            ->and(headerMenu($html))->toBe(['Host item', '|', 'Echo']);
    });

    it('draws no separator in a menu with only the host\'s items, and none before toned rows when nothing precedes them', function () {
        $hostOnly = renderBlade('<atom:page.header title="T"><x-slot:menu><atom:menu.item>A</atom:menu.item><atom:menu.item>B</atom:menu.item></x-slot:menu></atom:page.header>');
        $tonedOnly = renderBlade('<atom:page.header title="T"><x-slot:menu><atom:action tone="warning" wire:click="w">Echo</atom:action></x-slot:menu></atom:page.header>');

        expect(headerMenu($hostOnly))->toBe(['A', 'B'])
            ->and(headerMenu($tonedOnly))->toBe(['Echo']);
    });

    it('counts only the actions against the cap: the host\'s buttons are extra', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions><atom:button id="b1">B1</atom:button><atom:button id="b2">B2</atom:button><atom:button id="b3">B3</atom:button>
            <atom:action wire:click="a">Alpha</atom:action><atom:action wire:click="b">Bravo</atom:action><atom:action wire:click="c">Charlie</atom:action></x-slot:actions></atom:page.header>');

        expect(headerActions($html))->toBe(['b1', 'b2', 'b3', 'Alpha:visible', 'Bravo:visible', 'menu']);
    });
});

describe('Blade control flow', function () {
    it('places nothing for an `@if` that is false, and draws no empty group (a Livewire view prints block markers)', function () {
        $html = actionLivewire('<atom:page.header title="T"><x-slot:actions>@if (false) <atom:action wire:click="a">Alpha</atom:action> @endif</x-slot:actions><x-slot:menu>@if (false) <atom:action wire:click="b">Bravo</atom:action> @endif</x-slot:menu></atom:page.header>');

        expect(domQuery($html, '//*[@data-atom-page-actions]'))->toBeEmpty()
            ->and($html)->not->toContain('atom:action:');
    });

    it('keeps the order of a `@foreach` and a true `@if`', function () {
        $html = actionLivewire('<atom:page.header title="T"><x-slot:actions>
            @foreach ($names as $name) <atom:action wire:click="go(\'{{ $name }}\')" :label="$name" /> @endforeach
            @if (true) <atom:action wire:click="z">Zulu</atom:action> @endif
        </x-slot:actions></atom:page.header>', ['names' => ['Alpha', 'Bravo']]);

        expect(headerActions($html))->toBe(['Alpha:visible', 'Bravo:visible', 'menu'])
            ->and(headerMenu($html))->toBe(['Zulu']);
    });

    it('places an action written in an included partial', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions><x-action-partial /></x-slot:actions></atom:page.header>');

        expect(headerActions($html))->toBe(['From a partial:visible']);
    });

    it('places through a host component that wraps the header', function () {
        $html = renderBlade('<x-action-host-wrapper><atom:action wire:click="a">Alpha</atom:action><atom:action tone="delete" wire:click="d">Delete</atom:action></x-action-host-wrapper>');

        expect(headerActions($html))->toBe(['Alpha:visible', 'menu'])
            ->and(headerMenu($html))->toBe(['Delete']);
    });

    it('keeps two headers on one page apart', function () {
        $html = renderBlade('<atom:page.header title="One"><x-slot:actions><atom:action wire:click="a">Alpha</atom:action></x-slot:actions></atom:page.header>
            <atom:page.header title="Two"><x-slot:actions><atom:action wire:click="b">Bravo</atom:action></x-slot:actions></atom:page.header>');
        $groups = domQuery($html, '//*[@data-atom-page-actions]');

        expect($groups)->toHaveCount(2)
            ->and(actionText($groups[0]))->toBe('Alpha')
            ->and(actionText($groups[1]))->toBe('Bravo');
    });
});

describe('keys, markers and the registry', function () {
    it('gives each placed element a derived wire:key, unique for a repeated label, and lets the host\'s win', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>
            <atom:action wire:click="a">Save as</atom:action><atom:action wire:click="b">Save as</atom:action><atom:action wire:key="mine" wire:click="c">Third</atom:action>
        </x-slot:actions></atom:page.header>');
        $keys = array_map(fn ($el) => $el->getAttribute('wire:key'), domQuery($html, '//*[@data-atom-action]'));

        expect($keys)->toBe(['atom-action-save-as', 'atom-action-save-as-2', 'mine'])
            ->and($html)->toContain('data-atom-action="visible"', 'data-atom-action="menu"');
    });

    it('never prints a marker, and leaves nothing waiting in the registry', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>'.HEADER_FIVE.'</x-slot:actions></atom:page.header>');

        expect($html)->not->toContain('atom:action:')
            ->and(app(ActionItems::class)->pending())->toBe(0);
    });

    it('strips a forged or stale marker from a slot and places nothing for it', function () {
        $forged = '<!--atom:action:'.str_repeat('0', 32).'-->';
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>'.$forged.'</x-slot:actions><x-slot:menu><b id="m">x</b>'.$forged.'</x-slot:menu></atom:page.header>');

        expect($html)->not->toContain('atom:action:')
            ->and(headerActions($html))->toBe(['menu']);
    });

    it('does not take an action of a Livewire child rendered inside the header\'s slot', function () {
        Livewire::component('action-child', new class extends Component {
            public function render()
            {
                return '<div><atom:action wire:click="child">Child</atom:action></div>';
            }
        }::class);

        config(['cache.default' => 'array']);
        Log::spy();

        $html = actionLivewire('<atom:page.header title="T"><x-slot:actions><atom:action wire:click="a">Alpha</atom:action><livewire:action-child /></x-slot:actions></atom:page.header>');

        // the child's own element prints where the host wrote it, with no button in it
        expect(headerActions($html))->toBe(['div', 'Alpha:visible'])
            ->and(domQuery($html, '//*[@data-atom-page-actions]/div//button'))->toBeEmpty()
            ->and($html)->not->toContain('atom:action:')
            ->and($html)->not->toContain('Child')
            // nobody took it, so the request's end reports it (Livewire's test harness ends the request itself)
            ->and(app(ActionItems::class)->pending())->toBe(0);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => count($context['actions']) === 1 && ! str_contains(json_encode($context), 'Child'));
    });
});
