<?php

use Illuminate\View\ComponentAttributeBag;

// <atom:action._item>: how one placed action looks on each surface. page.header is the only surface wired in this change; the others'
// looks live here so the surfaces that follow only call it. Rendered directly, with the item the registry would hold.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** One placed action's element */
function placedElement(array $item, string $place, string $surface, bool $small = false): DOMElement
{
    $item = [
        'label' => 'Void invoice', 'html' => 'Void invoice', 'icon' => null, 'tone' => null, 'confirm' => null, 'href' => null,
        'newtab' => false, 'passphrase' => '', 'key' => 'atom-action-void-invoice', 'attributes' => new ComponentAttributeBag(['wire:click' => 'void']),
        ...$item,
    ];

    $html = renderBlade('<atom:action._item :item="$item" :place="$place" :surface="$surface" :small="$small" />', compact('item', 'place', 'surface', 'small'));

    return domQuery($html, '//*[@data-atom-action]')[0];
}

describe('visible buttons', function () {
    it('are bordered in a header and the bulk bar, and `sm` in a size="sm" header', function () {
        $header = placedElement([], 'visible', 'header');
        $small = placedElement([], 'visible', 'header', small: true);
        $bulk = placedElement([], 'visible', 'bulk');

        expect(domClasses($header))->toContain('border-control-border', 'h-control')
            ->and(domClasses($small))->toContain('h-control-sm')
            ->and(domClasses($bulk))->toContain('border-control-border', 'h-control')
            ->and($header->getAttribute('wire:key'))->toBe('atom-action-void-invoice');
    });

    it('are ghost in a toolbar, and an icon one keeps its label for a screen reader below `sm`', function () {
        $plain = placedElement([], 'visible', 'toolbar');
        $icon = placedElement(['icon' => 'download'], 'visible', 'toolbar');

        expect(domClasses($plain))->toContain('bg-transparent')
            ->and(domQuery($plain->ownerDocument->saveHTML($plain), '//*[contains(@class, "max-sm:sr-only")]'))->toBeEmpty()
            ->and(domClasses($icon))->toContain('bg-transparent')
            ->and(domQuery($icon->ownerDocument->saveHTML($icon), '//span[contains(@class, "max-sm:sr-only")]'))->toHaveCount(1)
            ->and(actionText($icon))->toBe('Void invoice');
    });

    it('keeps the confirm copy of an icon-only toolbar label, which is markup', function () {
        $button = placedElement(['icon' => 'archive', 'tone' => 'warning', 'label' => 'Archive', 'html' => 'Archive'], 'menu', 'toolbar');
        $icon = placedElement(['icon' => 'archive', 'confirm' => true, 'label' => 'Archive', 'html' => 'Archive'], 'visible', 'toolbar');

        expect(json_decode($icon->getAttribute('data-atom-confirm'), true))->toMatchArray(['heading' => 'Archive?', 'buttonConfirm' => 'Archive'])
            ->and(json_decode($button->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'warning', 'heading' => 'Archive?']);
    });

    it('are a link for an href in a card or panel, a ghost button otherwise: `xs` in a card, `sm` in a panel', function () {
        $cardLink = placedElement(['href' => '/all', 'attributes' => new ComponentAttributeBag], 'visible', 'card');
        $card = placedElement([], 'visible', 'card');
        $panel = placedElement([], 'visible', 'panel');
        $gated = placedElement(['href' => '/all', 'confirm' => true, 'attributes' => new ComponentAttributeBag], 'visible', 'card');

        expect($cardLink->nodeName)->toBe('a')
            ->and(domClasses($cardLink))->toContain('text-accent-ink', 'text-xs')
            ->and($cardLink->getAttribute('href'))->toBe('/all')
            ->and(domClasses($card))->toContain('bg-transparent', 'h-control-xs')
            ->and(domClasses($panel))->toContain('bg-transparent', 'h-control-sm')
            ->and(domClasses(placedElement(['href' => '/all', 'attributes' => new ComponentAttributeBag], 'visible', 'panel')))->toContain('text-sm')
            ->and($gated->hasAttribute('data-atom-confirm'))->toBeTrue()
            ->and($gated->getAttribute('data-atom-action'))->toBe('visible');
    });
});

describe('the primary and the pin', function () {
    it('is the accent button', function () {
        expect(domClasses(placedElement([], 'primary', 'header')))->toContain('bg-accent');
    });

    it('is the tint in the bulk bar: type="delete" for a delete, an inverted danger for a danger action', function () {
        $delete = placedElement(['tone' => 'delete', 'attributes' => new ComponentAttributeBag], 'pinned', 'bulk');
        $danger = placedElement(['tone' => 'danger'], 'pinned', 'bulk');

        expect(domClasses($delete))->toContain('bg-danger-bg', 'text-danger-fg')
            ->and($delete->getAttribute('x-on:confirmed'))->toBe('$wire.delete()')
            ->and(domClasses($danger))->toContain('bg-danger-bg', 'text-danger-fg')
            ->and(json_decode($danger->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'danger']);
    });
});

describe('menu rows', function () {
    it('take the tone as the item\'s variant on every surface', function (string $surface) {
        $neutral = placedElement([], 'menu', $surface);
        $warning = placedElement(['tone' => 'warning'], 'menu', $surface);
        $danger = placedElement(['tone' => 'danger'], 'menu', $surface);
        $delete = placedElement(['tone' => 'delete', 'attributes' => new ComponentAttributeBag], 'menu', $surface);

        expect($neutral->getAttribute('role'))->toBe('menuitem')
            ->and(domClasses($neutral))->toContain('text-fg')
            ->and(domClasses($warning))->toContain('text-warning-fg')
            ->and(domClasses($danger))->toContain('text-danger-fg')
            ->and(domClasses($delete))->toContain('text-danger-fg')
            ->and($delete->getAttribute('x-on:confirmed'))->toBe('$wire.delete()')
            ->and($warning->getAttribute('data-atom-action'))->toBe('menu');
    })->with(['header', 'toolbar', 'bulk', 'card', 'panel', 'row']);
});
