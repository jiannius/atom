<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\TileChildFixture;
use Jiannius\Atom\Tests\Fixtures\TileParentFixture;
use Livewire\Livewire;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('checkbox', function () {
    it('renders a checkbox and applies the name from wire:model', function () {
        // Regression: the component built $merges with the name but rendered
        // {{ $attributes }} instead of {{ $attributes->merge($merges) }}.
        $html = renderBlade('<atom:checkbox wire:model="agree" label="I agree" />');

        expect($html)
            ->toContain('data-atom-checkbox')
            ->toContain('type="checkbox"')
            ->toContain('name="agree"')
            ->toContain('I agree');
    });

    it('keeps the real input operable instead of a fake role=checkbox div', function () {
        // a11y: the input is sr-only (focusable + in the a11y tree, Space
        // toggles natively); the visual swatch is decorative aria-hidden, not
        // a role/tabindex div that no keyboard handler ever wired up.
        $html = renderBlade('<atom:checkbox label="x" />');

        expect($html)
            ->toContain('class="sr-only peer"')
            ->toContain('aria-hidden="true"')
            ->toContain('peer-focus-visible:outline-2')
            ->not->toContain('role="checkbox"')
            ->not->toContain('tabindex');
    });

    it('renders a caption', function () {
        $html = renderBlade('<atom:checkbox label="x" caption="Helper" />');

        expect($html)->toContain('data-atom-caption')->toContain('Helper');
    });
});

describe('radio', function () {
    it('prints a caption under the label inside the same <label>, in a card group too (#161)', function () {
        $html = renderBlade('<atom:radio.group variant="card"><atom:radio name="b" value="y" label="Yearly" caption="RM 500 a year" align="start"/></atom:radio.group>');
        $labels = domQuery($html, '//label[@data-atom-radio]');

        expect($labels)->toHaveCount(1)
            ->and($labels[0]->textContent)->toContain('Yearly')->toContain('RM 500 a year')
            // the dot sits on the first line
            ->and(domQuery($html, '//label[@data-atom-radio]//div[contains(@class, "items-start")]'))->toHaveCount(1)
            // and the panel is the card's
            ->and($html)->toContain('divide-y');
    });

    it('renders a native radio with the decorative swatch hidden from AT', function () {
        $html = renderBlade('<atom:radio label="Option A" />');

        expect($html)
            ->toContain('data-atom-radio')
            ->toContain('type="radio"')
            ->toContain('class="sr-only peer"')
            ->toContain('aria-hidden="true"')
            ->not->toContain('role="checkbox"')
            ->not->toContain('role="radio"')   // native input carries the role
            ->not->toContain('tabindex')
            ->toContain('group-has-[.error]/radio:outline-1')
            ->not->toContain('group-has-[.error]/checkbox');
    });
});

describe('toggle', function () {
    it('renders a checkbox-backed toggle with the name applied', function () {
        $html = renderBlade('<atom:toggle wire:model="enabled" label="Enabled" />');

        expect($html)
            ->toContain('data-atom-toggle')
            ->toContain('type="checkbox"')
            ->toContain('name="enabled"')
            ->toContain('Enabled');
    });

    it('keeps the real input operable instead of a fake role=checkbox div', function () {
        $html = renderBlade('<atom:toggle label="x" />');

        expect($html)
            ->toContain('class="peer sr-only"')
            ->toContain('aria-hidden="true"')
            ->toContain('peer-focus-visible:outline-2')
            ->not->toContain('role="checkbox"')
            ->not->toContain('tabindex');
    });
});

describe('selectable tiles (variant="tile", issue #213)', function () {
    $radios = '<atom:radio.group label="Plan" variant="tile"><atom:radio name="plan" value="starter" label="Starter" caption="RM 50 a month"/><atom:radio name="plan" value="growth"><b>Growth</b></atom:radio></atom:radio.group>';
    $checkboxes = '<atom:checkbox.group variant="tile" role="group" aria-label="Extras"><atom:checkbox wire:model="extras" value="gift" label="Gift wrap" caption="RM 5"/><atom:checkbox name="note" label="Note"/></atom:checkbox.group>';

    it('draws a radio tile as a label around its native input, which stays a real sr-only radio', function () use ($radios) {
        $html = renderBlade($radios);
        $tiles = domQuery($html, '//label[@data-atom-radio][@data-atom-tile]');

        expect($tiles)->toHaveCount(2)
            // the input is the label's own child, not display:none, not a role=radio div
            ->and(domQuery($html, '//label[@data-atom-tile]/input[@type="radio"][contains(@class, "sr-only")]'))->toHaveCount(2)
            ->and(domQuery($html, '//label[@data-atom-tile]/input[@name="plan"]'))->toHaveCount(2)
            ->and(domQuery($html, '//label[@data-atom-tile]/input[@value="starter"]'))->toHaveCount(1)
            ->and($html)->not->toContain('role="radio"')->not->toContain('tabindex')
            // the box is the panel radius, a border and the surface fill
            ->and(domClasses($tiles[0]))->toContain('rounded-panel')->toContain('border')->toContain('bg-surface')->toContain('p-3')
            // the swatch is decorative, over the top end
            ->and(domQuery($html, '//label[@data-atom-tile]/div[@aria-hidden="true"][@data-atom-tile-indicator]'))->toHaveCount(2)
            // label, caption and a slot are all inside the one label, so the control is named by them
            ->and($tiles[0]->textContent)->toContain('Starter')->toContain('RM 50 a month')
            ->and($tiles[1]->textContent)->toContain('Growth');
    });

    it('makes the group a grid of tiles, named by its field label, and keeps the variant attribute off the element', function () use ($radios) {
        $html = renderBlade($radios);
        $group = domQuery($html, '//div[@data-atom-tiles]');

        expect($group)->toHaveCount(1)
            ->and($group[0]->getAttribute('role'))->toBe('radiogroup')
            ->and($group[0]->getAttribute('aria-labelledby'))->toBe(domQuery($html, '//label[@data-atom-label]')[0]->getAttribute('id'))
            ->and($group[0]->hasAttribute('variant'))->toBeFalse()
            ->and(domQuery($html, '//div[@data-atom-tiles]//label[@data-atom-tile]'))->toHaveCount(2);

        // a checkbox group is the same grid, and a host names it with the attributes it forwards
        $boxes = domQuery(renderBlade('<atom:checkbox.group variant="tile" role="group" aria-label="Extras"><atom:checkbox label="A"/></atom:checkbox.group>'), '//div[@data-atom-tiles]');
        expect($boxes)->toHaveCount(1)->and($boxes[0]->getAttribute('aria-label'))->toBe('Extras')->and($boxes[0]->getAttribute('role'))->toBe('group');
    });

    it('draws a checkbox tile the same way, with the name from wire:model and its error inside the label', function () use ($checkboxes) {
        $html = renderBlade($checkboxes);
        $tiles = domQuery($html, '//label[@data-atom-checkbox][@data-atom-tile]');

        expect($tiles)->toHaveCount(2)
            ->and(domQuery($html, '//label[@data-atom-tile]/input[@type="checkbox"][contains(@class, "sr-only")][@name="extras"]'))->toHaveCount(1)
            ->and($tiles[0]->textContent)->toContain('Gift wrap')->toContain('RM 5')
            ->and($html)->not->toContain('role="checkbox"')->not->toContain('tabindex');

        $error = domQuery(renderBlade('<atom:checkbox.group variant="tile"><atom:checkbox name="t" label="Terms" error="Accept the terms"/></atom:checkbox.group>'), '//label[@data-atom-tile]//div[@data-atom-error]');
        expect($error)->toHaveCount(1)->and($error[0]->textContent)->toContain('Accept the terms');
    });

    it('does not draw a tile outside a tile group, nor in the default and card variants (their markup is unchanged)', function () {
        foreach ([
            '<atom:radio name="a" value="1" label="One"/>',
            '<atom:radio.group><atom:radio name="a" value="1" label="One"/></atom:radio.group>',
            '<atom:radio.group label="Billing" variant="card"><atom:radio name="b" value="y" label="Yearly" caption="RM 500" align="start"/></atom:radio.group>',
            '<atom:checkbox label="One"/>',
            '<atom:checkbox.group><atom:checkbox label="One"/></atom:checkbox.group>',
            '<atom:checkbox.group variant="card"><atom:checkbox label="One"/></atom:checkbox.group>',
        ] as $template) {
            expect(renderBlade($template))->not->toContain('data-atom-tile')->not->toContain('data-atom-tiles');
        }

        $card = renderBlade('<atom:radio.group variant="card"><atom:radio name="b" value="y" label="Yearly"/></atom:radio.group>');
        expect($card)->toContain('divide-y')->toContain('[data-atom-radio]]:py-3')->toContain('<div class="size-1.5 opacity-0 rounded-full bg-surface"></div>');
        expect(renderBlade('<atom:checkbox.group variant="card"><atom:checkbox label="One"/></atom:checkbox.group>'))->toContain('divide-y');
        expect(renderBlade('<atom:radio.group><atom:radio name="b" value="y" label="Yearly"/></atom:radio.group>'))->toContain('flex flex-col gap-2');
    });

    it('does not make a lone radio or checkbox a tile because a component around it has variant="tile"', function () {
        Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');

        foreach ([
            '<atom:card variant="tile">%s</atom:card>',
            '<atom:modal variant="tile">%s</atom:modal>',
            '<atom:tabs variant="tile">%s</atom:tabs>',
            '<x-tile-wrapper variant="tile">%s</x-tile-wrapper>',
            '<atom:radio.group variant="card"><x-tile-wrapper variant="tile">%s</x-tile-wrapper></atom:radio.group>',
        ] as $template) {
            expect(renderBlade(str_replace('%s', '<atom:radio name="a" value="1" label="One"/><atom:checkbox label="Two"/>', $template)))
                ->toContain('data-atom-radio')->toContain('data-atom-checkbox')->not->toContain('data-atom-tile');
        }
    });

    it('draws tiles when a wrapper with a variant of its own sits between the group and its options', function () {
        Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');

        foreach ([
            '<atom:card variant="subtle">%s</atom:card>',
            '<atom:tooltip variant="dark">%s</atom:tooltip>',
            '<x-tile-wrapper variant="featured">%s</x-tile-wrapper>',
            '<x-tile-wrapper variant="featured"><x-tile-wrapper>%s</x-tile-wrapper></x-tile-wrapper>',
        ] as $wrapper) {
            $radios = renderBlade('<atom:radio.group variant="tile">'.str_replace('%s', '<atom:radio name="a" value="1" label="One"/>', $wrapper).'</atom:radio.group>');
            $boxes = renderBlade('<atom:checkbox.group variant="tile">'.str_replace('%s', '<atom:checkbox label="Two"/>', $wrapper).'</atom:checkbox.group>');

            expect(domQuery($radios, '//label[@data-atom-radio][@data-atom-tile]'))->toHaveCount(1, $wrapper)
                ->and(domQuery($boxes, '//label[@data-atom-checkbox][@data-atom-tile]'))->toHaveCount(1, $wrapper);
        }
    });

    it('reads the variant however it is written on the group: literal, bound, or an attribute bag', function () {
        Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');
        $tiles = fn (string $html) => count(domQuery($html, '//label[@data-atom-tile]'));
        $option = '<atom:radio name="a" value="1" label="One"/>';

        expect($tiles(renderBlade('<atom:radio.group variant="tile">'.$option.'</atom:radio.group>')))->toBe(1)
            ->and($tiles(renderBlade('<atom:radio.group :variant="$v">'.$option.'</atom:radio.group>', ['v' => 'tile'])))->toBe(1)
            ->and($tiles(renderBlade('<atom:radio.group :variant="$v">'.$option.'</atom:radio.group>', ['v' => 'card'])))->toBe(0)
            ->and($tiles(renderBlade('<atom:radio.group :variant="$v">'.$option.'</atom:radio.group>', ['v' => null])))->toBe(0)
            ->and($tiles(renderBlade('<atom:radio.group label="Pick" variant="tile">'.$option.'</atom:radio.group>')))->toBe(1)
            ->and($tiles(renderBlade('<atom:checkbox.group :variant="$v"><atom:checkbox label="Two"/></atom:checkbox.group>', ['v' => 'tile'])))->toBe(1)
            ->and($tiles(renderBlade('<x-tile-group variant="tile"/>')))->toBe(1)
            ->and($tiles(renderBlade('<x-tile-group/>')))->toBe(0);
    });

    it('keeps a default or card group nested in a tile slot plain, and its own options', function () {
        $html = renderBlade('<atom:checkbox.group variant="tile"><atom:checkbox label="Outer"/><atom:checkbox label="Wrapper"><atom:checkbox.group><atom:checkbox label="Inner"/></atom:checkbox.group><atom:radio.group variant="card"><atom:radio name="a" value="1" label="Card"/></atom:radio.group></atom:checkbox></atom:checkbox.group>');
        $tiles = collect(domQuery($html, '//label[@data-atom-tile]'))->map(fn ($el) => preg_replace('/\s+/', ' ', trim($el->textContent)))->all();

        expect($tiles)->toHaveCount(2)
            ->and(domQuery($html, '//label[@data-atom-checkbox and not(@data-atom-tile)][contains(., "Inner")]'))->toHaveCount(1)
            ->and(domQuery($html, '//label[@data-atom-radio and not(@data-atom-tile)][contains(., "Card")]'))->toHaveCount(1);

        // and a tile group in a default one's slot is tiled again
        $again = renderBlade('<atom:checkbox.group><atom:checkbox.group variant="tile"><atom:checkbox label="In"/></atom:checkbox.group><atom:checkbox label="Out"/></atom:checkbox.group>');
        expect(domQuery($again, '//label[@data-atom-tile]'))->toHaveCount(1)->and(domQuery($again, '//label[@data-atom-tile][contains(., "In")]'))->toHaveCount(1);
    });

    it('draws a Livewire child of a tile group the same on its first render and after its own update', function () {
        Livewire::component('atom-test-tile-child', TileChildFixture::class);

        $parent = Livewire::test(TileParentFixture::class);
        $first = domQuery($parent->html(), '//*[@data-child]//label');
        $alone = domQuery(Livewire::test(TileChildFixture::class)->call('bump')->html(), '//*[@data-child]//label');

        // the child's own options are plain on both renders; the parent's own checkbox is the one tile
        expect($first)->toHaveCount(2)->and($alone)->toHaveCount(2)
            ->and(domQuery($parent->html(), '//label[@data-atom-tile]'))->toHaveCount(1)
            ->and(domQuery($parent->html(), '//*[@data-child]//label[@data-atom-tile]'))->toHaveCount(0)
            ->and(domQuery(Livewire::test(TileChildFixture::class)->call('bump')->html(), '//label[@data-atom-tile]'))->toHaveCount(0);
    });

    it('does not print the key a group publishes as an attribute', function () {
        foreach ([
            '<atom:radio.group variant="tile"><atom:radio name="a" value="1" label="One"/></atom:radio.group>',
            '<atom:radio.group label="Pick" variant="card"><atom:radio name="a" value="1" label="One"/></atom:radio.group>',
            '<atom:radio.group><atom:radio name="a" value="1" label="One"/></atom:radio.group>',
            '<atom:checkbox.group variant="tile"><atom:checkbox label="One"/></atom:checkbox.group>',
            '<atom:checkbox.group variant="card"><atom:checkbox label="One"/></atom:checkbox.group>',
            '<atom:checkbox.group><atom:checkbox label="One"/></atom:checkbox.group>',
        ] as $template) {
            expect(strtolower(renderBlade($template)))->not->toContain('atomchoicetile');
        }
    });

    it('is a real disabled input when the tile, its group or its form is disabled, and its own disabled wins', function () {
        $state = fn (string $template) => collect(domQuery(renderBlade($template), '//label[@data-atom-tile]/input'))
            ->map(fn ($input) => $input->getAttribute('data-atom-disabled').($input->hasAttribute('disabled') ? '+disabled' : ''))->all();

        expect($state('<atom:radio.group variant="tile"><atom:radio name="a" value="1" label="One" disabled/><atom:radio name="a" value="2" label="Two"/></atom:radio.group>'))->toBe(['self+disabled', ''])
            ->and($state('<atom:radio.group variant="tile" disabled><atom:radio name="a" value="1" label="One"/><atom:radio name="a" value="2" label="Two" :disabled="false"/></atom:radio.group>'))->toBe(['inherited+disabled', ''])
            ->and($state('<atom:checkbox.group variant="tile" disabled><atom:checkbox label="One"/><atom:checkbox label="Two" :disabled="false"/></atom:checkbox.group>'))->toBe(['inherited+disabled', ''])
            ->and($state('<atom:form disabled><atom:checkbox.group variant="tile"><atom:checkbox label="One"/></atom:checkbox.group><atom:radio.group variant="tile"><atom:radio name="a" value="1" label="Two"/></atom:radio.group></atom:form>'))->toBe(['inherited+disabled', 'inherited+disabled']);

        // the indicator and the text dim from the input's own state, so they follow it
        expect(renderBlade('<atom:radio.group variant="tile"><atom:radio name="a" value="1" label="One" disabled/></atom:radio.group>'))
            ->toContain('peer-disabled:opacity-50')->toContain('peer-disabled:text-fg-subtle');
    });
});
