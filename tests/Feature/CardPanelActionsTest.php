<?php

use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Services\ActionItems;
use Livewire\Livewire;

// <atom:action> in the heading row of <atom:card> and <atom:panel> (components/_heading-row.blade.php): the row collects its `actions` and `menu`
// slots, places the actions (Services\ActionPlacement, one visible and the rest in the ⋯) and draws each once: a link for an `href`, else a ghost
// button (`xs` in a card, `sm` in a panel). The body is never read. What the row prints for a card with no action is proven by ActionGoldenTest.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** Render Blade inside a Livewire component, so the markers of an `@if` or `@foreach` are compiled in */
function cardActionsLivewire(string $blade, array $data = []): string
{
    $component = new class extends \Livewire\Component {
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
 * What the row's action group draws, child by child: `Label:place` for an action, `menu` for the ⋯ dropdown, `<tag>` for host markup.
 *
 * @return list<string>
 */
function rowActions(string $html): array
{
    $group = domQuery($html, '//*[@data-atom-heading-row-actions]')[0] ?? null;
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
 * The ⋯ menu's children, in order: the row's text, `|` for a separator.
 *
 * @return list<string>
 */
function rowMenu(string $html): array
{
    $menu = domQuery($html, '//*[@data-atom-heading-row-actions]//*[@data-atom-menu]')[0] ?? null;
    $rows = [];

    foreach ($menu?->childNodes ?? [] as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $rows[] = $child->hasAttribute('data-atom-menu-separator') ? '|' : actionText($child);
    }

    return $rows;
}

const CARD_FOUR = '<atom:action wire:click="a">Alpha</atom:action>
    <atom:action wire:click="b">Bravo</atom:action>
    <atom:action priority="low" wire:click="c">Charlie</atom:action>
    <atom:action tone="warning" wire:click="d">Delta</atom:action>
    <atom:action tone="delete" wire:click="e">Echo</atom:action>';

describe('what shows', function () {
    foreach (['card', 'panel'] as $surface) {
        it("draws one button in a $surface, the rest in the ⋯ menu after a separator, never a toned one as a button", function () use ($surface) {
            $html = renderBlade('<atom:'.$surface.' heading="Parts"><x-slot:actions>'.CARD_FOUR.'</x-slot:actions> body</atom:'.$surface.'>');

            expect(rowActions($html))->toBe(['Alpha:visible', 'menu'])
                ->and(rowMenu($html))->toBe(['Bravo', 'Charlie', '|', 'Delta', 'Echo']);
        });
    }

    it('draws a card\'s button as a ghost xs button and a panel\'s as a ghost sm one', function () {
        $card = renderBlade('<atom:card heading="Parts"><x-slot:actions><atom:action wire:click="go">Refresh</atom:action></x-slot:actions> b</atom:card>');
        $panel = renderBlade('<atom:panel heading="Parts"><x-slot:actions><atom:action wire:click="go">Refresh</atom:action></x-slot:actions> b</atom:panel>');
        $xs = domQuery($card, '//button[@data-atom-action="visible"]')[0];
        $sm = domQuery($panel, '//button[@data-atom-action="visible"]')[0];

        expect(domClasses($xs))->toContain('h-control-xs')->not->toContain('h-control-sm')
            ->and(domClasses($sm))->toContain('h-control-sm')->not->toContain('h-control-xs');
    });

    it('draws an action with an href as a link, 12px in a card and 14px in a panel', function () {
        $card = renderBlade('<atom:card heading="Parts"><x-slot:actions><atom:action href="/parts">View all</atom:action></x-slot:actions> b</atom:card>');
        $panel = renderBlade('<atom:panel heading="Parts"><x-slot:actions><atom:action href="/parts">View all</atom:action></x-slot:actions> b</atom:panel>');
        $cardLink = domQuery($card, '//a[@data-atom-action="visible"]');
        $panelLink = domQuery($panel, '//a[@data-atom-action="visible"]');

        expect($cardLink)->toHaveCount(1)
            ->and($cardLink[0]->getAttribute('href'))->toBe('/parts')
            ->and(domClasses($cardLink[0]))->toContain('text-xs')
            ->and(domQuery($card, '//button[@data-atom-action="visible"]'))->toBeEmpty()
            ->and($panelLink)->toHaveCount(1)
            ->and(domClasses($panelLink[0]))->toContain('text-sm');
    });

    it('draws a second action with an href in the ⋯ as a row, and drops a javascript: href', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:actions>
            <atom:action href="/a">One</atom:action><atom:action href="/b">Two</atom:action><atom:action href="javascript:alert(1)">Bad</atom:action>
        </x-slot:actions> b</atom:card>');

        expect(rowActions($html))->toBe(['One:visible', 'menu'])
            ->and(domQuery($html, '//*[@data-atom-menu]//a[@href="/b"]'))->toHaveCount(1)
            ->and($html)->not->toContain('javascript:');
    });

    it('keeps a toned action out of the buttons even when it stands alone, and asks first in its tone', function () {
        $html = renderBlade('<atom:panel heading="Parts"><x-slot:actions><atom:action tone="delete" wire:click="clear">Clear all</atom:action></x-slot:actions> b</atom:panel>');
        $row = domQuery($html, '//*[@data-atom-menu]/*[@data-atom-action="menu"]')[0];

        expect(rowActions($html))->toBe(['menu'])
            ->and(rowMenu($html))->toBe(['Clear all'])
            ->and($row->hasAttribute('data-atom-confirm'))->toBeTrue();
    });

    it('takes the actions of the menu slot as low: they go to the ⋯ and are never the button', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:menu><atom:action wire:click="a">Export</atom:action><atom:action wire:click="b">Archive</atom:action></x-slot:menu> b</atom:card>');

        expect(rowActions($html))->toBe(['menu'])
            ->and(rowMenu($html))->toBe(['Export', 'Archive']);
    });

    it('is rendered once per action: each wire:click occurs once in the output', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:actions>'.CARD_FOUR.'</x-slot:actions> b</atom:card>');

        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            expect(substr_count($html, 'wire:click="'.$name.'"'))->toBe(1);
        }
    });

    it('draws a row from an action alone, with no heading, and names the ⋯ plainly', function () {
        $html = renderBlade('<atom:card><x-slot:actions><atom:action wire:click="a">Alpha</atom:action><atom:action wire:click="b">Bravo</atom:action></x-slot:actions> body</atom:card>');

        expect(rowActions($html))->toBe(['Alpha:visible', 'menu'])
            ->and(domQuery($html, '//*[@data-atom-heading-row-title]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]//button[@aria-label]')[0]->getAttribute('aria-label'))->toBe('More actions');
    });

    it('keeps the panel\'s count beside the title', function () {
        $html = renderBlade('<atom:panel heading="Lines" :count="12"><x-slot:actions><atom:action wire:click="a">Alpha</atom:action></x-slot:actions> b</atom:panel>');

        expect(domQuery($html, '//*[@data-atom-heading-row-count]')[0]->textContent)->toBe('12')
            ->and(rowActions($html))->toBe(['Alpha:visible']);
    });
});

describe('the ⋯ is named for the box', function () {
    it('says "More actions for <heading>" in a card and in a panel, for an engine-only menu too', function () {
        foreach (['card' => 'Parts used', 'panel' => 'Lines'] as $surface => $heading) {
            $html = renderBlade('<atom:'.$surface.' heading="'.$heading.'"><x-slot:actions>'.CARD_FOUR.'</x-slot:actions> b</atom:'.$surface.'>');
            $more = domQuery($html, '//*[@data-atom-heading-row-actions]//*[@data-atom-dropdown]//button')[0];

            expect($more->getAttribute('aria-label'))->toBe('More actions for '.$heading);
        }
    });

    it('names it from the plain text of a heading slot', function () {
        $html = renderBlade('<atom:card><x-slot:heading>Parts <b>used</b></x-slot:heading><x-slot:menu><atom:action wire:click="a">Export</atom:action></x-slot:menu> b</atom:card>');

        expect(domQuery($html, '//*[@data-atom-heading-row-actions]//button[@aria-label]')[0]->getAttribute('aria-label'))->toBe('More actions for Parts used');
    });
});

describe('mixing with the host\'s own markup', function () {
    it('prints the host\'s actions first, then the engine\'s button, then the ⋯', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:actions><a id="host" href="/h">Host link</a><atom:action wire:click="a">Alpha</atom:action><atom:action wire:click="b">Bravo</atom:action></x-slot:actions> b</atom:card>');

        expect(rowActions($html))->toBe(['host', 'Alpha:visible', 'menu']);
    });

    it('prints the host\'s menu items before the engine\'s rows, with a separator before the first toned row', function () {
        $html = renderBlade('<atom:panel heading="Lines"><x-slot:menu><atom:menu.item>Host export</atom:menu.item><atom:action wire:click="a">Print</atom:action><atom:action tone="danger" wire:click="b">Purge</atom:action></x-slot:menu> b</atom:panel>');

        expect(rowMenu($html))->toBe(['Host export', 'Print', '|', 'Purge']);
    });

    it('draws a separator before a toned engine row that follows only the host\'s items', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:menu><atom:menu.item>Host export</atom:menu.item><atom:action tone="warning" wire:click="a">Archive</atom:action></x-slot:menu> b</atom:card>');

        expect(rowMenu($html))->toBe(['Host export', '|', 'Archive']);
    });

    it('draws no separator when nothing precedes the toned row', function () {
        $html = renderBlade('<atom:card heading="Parts"><x-slot:menu><atom:action tone="warning" wire:click="a">Archive</atom:action></x-slot:menu> b</atom:card>');

        expect(rowMenu($html))->toBe(['Archive']);
    });
});

describe('Blade control flow', function () {
    it('places nothing for an action behind a false @if, and draws no row for a slot of false blocks', function () {
        $html = cardActionsLivewire('<atom:card><x-slot:actions>@if (false) <atom:action wire:click="a">Alpha</atom:action> @endif</x-slot:actions> body</atom:card>');

        expect($html)->not->toContain('data-atom-heading-row')
            ->and($html)->not->toContain('atom:action:');
    });

    it('places the actions that pass an @if and an @foreach, in order', function () {
        $html = cardActionsLivewire('<atom:panel heading="Lines"><x-slot:actions>
            @if (true) <atom:action wire:click="a">Alpha</atom:action> @endif
            @foreach ($names as $name) <atom:action wire:click="go(\'{{ $name }}\')">{{ $name }}</atom:action> @endforeach
        </x-slot:actions> b</atom:panel>', ['names' => ['Bravo', 'Charlie']]);

        expect(rowActions($html))->toBe(['Alpha:visible', 'menu'])
            ->and(rowMenu($html))->toBe(['Bravo', 'Charlie']);
    });

    it('keeps a card inside a panel\'s body and a panel\'s own actions apart', function () {
        $html = renderBlade('<atom:panel heading="Outer"><x-slot:actions><atom:action wire:click="outer">Outer one</atom:action></x-slot:actions>
            <atom:card heading="Inner"><x-slot:actions><atom:action wire:click="inner">Inner one</atom:action></x-slot:actions> x</atom:card>
        </atom:panel>');

        expect(domQuery($html, '//*[@data-atom-panel]/*[@data-atom-heading-row]//*[@data-atom-action]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-card]//*[@data-atom-heading-row]//*[@data-atom-action]'))->toHaveCount(1)
            ->and(app(ActionItems::class)->pending())->toBe(0);
    });
});

describe('the body is not read', function () {
    it('draws nothing for an action in the body and reports it once, without its label', function () {
        config(['cache.default' => 'array']);
        Log::spy();

        $html = cardActionsLivewire('<atom:card heading="Parts">body <atom:action wire:click="stray">Secret label</atom:action></atom:card>');

        expect($html)->not->toContain('Secret label')
            ->and($html)->not->toContain('wire:click="stray"')
            ->and(app(ActionItems::class)->pending())->toBe(0);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => count($context['actions']) === 1 && ! str_contains(json_encode($context), 'Secret'));
    });

    it('draws nothing for an action in the panel\'s footer or heading slot, and the heading row is not drawn for it', function () {
        config(['cache.default' => 'array']);
        Log::spy();

        $html = cardActionsLivewire('<atom:panel><x-slot:footer><atom:action wire:click="stray">Footer action</atom:action></x-slot:footer> body</atom:panel>');

        expect($html)->not->toContain('Footer action')
            ->and($html)->not->toContain('data-atom-heading-row');

        Log::shouldHaveReceived('warning')->once();
    });

    it('strips a forged marker from the actions and menu slots and draws no row for it', function () {
        $forged = '<!--atom:action:'.str_repeat('0', 32).'-->';
        $html = renderBlade('<atom:card><x-slot:actions>'.$forged.'</x-slot:actions><x-slot:menu>'.$forged.'</x-slot:menu> body</atom:card>');

        expect($html)->not->toContain('atom:action:')
            ->and($html)->not->toContain('data-atom-heading-row');
    });
});
