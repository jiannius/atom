<?php

/*
 * V4-3b: the card, callout, dd, list, avatar, profile, progress, skeleton, placeholder-bar and kbd on the role tokens.
 * The classes are held by RestyleGuardsTest (no palette name, no dark: pair, no shadow); these pin what each one draws.
 */

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/**
 * The first element with the given data attribute.
 */
function displayElement(string $template, string $attribute, array $data = []): DOMElement
{
    $found = domQuery(renderBlade($template, $data), '//*[@'.$attribute.']');

    expect($found)->not->toBeEmpty();

    return $found[0];
}

describe('card', function () {
    it('is a fill and a hairline at the panel radius, with no shadow', function () {
        $classes = domClasses(displayElement('<atom:card>x</atom:card>', 'data-atom-card'));

        expect($classes)->toContain('rounded-panel', 'border', 'bg-surface', 'p-4')
            ->and(array_filter($classes, fn ($c) => str_starts_with($c, 'shadow')))->toBe([]);
    });

    it('draws the subtle card on the sunken fill, still with the hairline', function () {
        $classes = domClasses(displayElement('<atom:card subtle>x</atom:card>', 'data-atom-card'));

        expect($classes)->toContain('bg-surface-sunken', 'border')->not->toContain('border-transparent', 'bg-surface');
    });

    it('divides on the hairline token, and pads each child of a divided card', function () {
        expect(domClasses(displayElement('<atom:card divided><div>a</div><div>b</div></atom:card>', 'data-atom-card')))
            ->toContain('divide-y', 'divide-border', '[&>div]:p-4');
    });

    it('has no padding when it is inset, and the cover bleeds by the padding it has', function () {
        expect(domClasses(displayElement('<atom:card inset>x</atom:card>', 'data-atom-card')))->not->toContain('p-4');

        $html = renderBlade('<atom:card>
            <x-slot:cover><img src="x"></x-slot:cover>
            body
        </atom:card>');

        expect($html)->toContain('-mx-4 -mt-4 mb-4');
    });

    it('keeps the stats variant: a figure in the tabular type, and an indicator in the success or danger ink', function () {
        $up = renderBlade('<atom:card variant="stats" heading="Sales" data="RM 1" :indicator="5" />');
        $down = renderBlade('<atom:card variant="stats" heading="Sales" data="RM 1" :indicator="-5" />');

        expect($up)->toContain('text-2xl font-semibold tabular-nums', 'text-success-fg', '5%')
            ->and($down)->toContain('text-danger-fg', '5%')
            ->and($up)->toContain('h-32');
    });
});

describe('callout', function () {
    it('draws each variant on its tone: the tint, a hairline of its ink and the icon in the ink', function (string $variant, string $tone) {
        $classes = domClasses(displayElement('<atom:callout :variant="$variant" heading="h" />', 'x-data', compact('variant')));

        expect($classes)->toContain("bg-{$tone}-bg", "border-{$tone}-fg/20", "[&_[data-atom-icon]]:text-{$tone}-fg", 'rounded-panel', 'text-fg');
    })->with([['info', 'info'], ['success', 'success'], ['warning', 'warning'], ['danger', 'danger'], ['error', 'danger']]);

    it('is the neutral tint with no variant', function () {
        expect(domClasses(displayElement('<atom:callout heading="h" />', 'x-data')))->toContain('bg-neutral-bg', 'border-border');
    });

    it('prints the heading in a marked element, for a string and for a slot', function () {
        $string = renderBlade('<atom:callout heading="Plain" />');
        $slot = renderBlade('<atom:callout>
            <x-slot:heading><b>Rich</b></x-slot:heading>
            body
        </atom:callout>');

        expect(domQuery($string, '//*[@data-atom-callout-heading]'))->toHaveCount(1)
            ->and(domQuery($slot, '//*[@data-atom-callout-heading]/b'))->toHaveCount(1);
    });

    it('draws the heading as text, not markup', function () {
        $html = renderBlade('<atom:callout :heading="$h" />', ['h' => '<img src=x onerror=alert(1)>']);

        expect(domQuery($html, '//*[@data-atom-callout-heading]//img'))->toBeEmpty();
    });

    it('keeps the text clear of the dismiss button, a 28px target with a 16px icon', function () {
        $html = renderBlade('<atom:callout heading="h" closeable />');

        expect(domClasses(domQuery($html, '//*[@x-data]')[0]))->toContain('pe-11')
            ->and($html)->toContain('size-7')
            ->and(domClasses(domQuery($html, '//button//span[@data-atom-icon]')[0]))->toContain('size-4');
        expect(domClasses(displayElement('<atom:callout heading="h" />', 'x-data')))->not->toContain('pe-11');
    });
});

describe('dd', function () {
    it('draws the label muted and the value in the foreground', function () {
        $html = renderBlade('<atom:dd label="Email">a@b.test</atom:dd>');

        expect(domClasses(domQuery($html, '//dt')[0]))->toContain('text-fg-muted')
            ->and(domClasses(domQuery($html, '//dd')[0]))->toContain('text-fg', 'min-w-0');
    });

    it('prints a label as text, and the filler for an empty value', function () {
        $html = renderBlade('<atom:dd :label="$l" filler="n/a" />', ['l' => '<i>x</i>']);

        expect(domQuery($html, '//dt//i'))->toBeEmpty()
            ->and(trim(domQuery($html, '//dd')[0]->textContent))->toBe('n/a');
    });
});

describe('list', function () {
    it('draws the heading as a 12px muted label, not uppercase', function () {
        $html = renderBlade('<atom:list heading="Files"><atom:list.item>a</atom:list.item></atom:list>');
        $heading = domQuery($html, '//*[@data-atom-list]/div[1]')[0];

        expect(domClasses($heading))->toContain('text-xs', 'text-fg-muted')->not->toContain('uppercase');
    });

    it('rules the rail with the hairline token and hovers a row on a tint of the foreground', function () {
        $html = renderBlade('<atom:list><atom:list.item>a</atom:list.item></atom:list>');

        expect(domClasses(domQuery($html, '//*[@data-atom-list]/div[1]')[0]))->toContain('border-border')
            ->and(domClasses(domQuery($html, '//*[@data-atom-list-item]')[0]))->toContain('rounded-control', 'hover:bg-fg/[0.04]');
    });

    it('sends a list item href through safe_url', function () {
        $html = renderBlade('<atom:list.item :href="$href">a</atom:list.item>', ['href' => 'javascript:alert(1)']);

        expect(domQuery($html, '//a'))->toBeEmpty();

        $html = renderBlade('<atom:list.item :href="$href">a</atom:list.item>', ['href' => 'https://example.test/x']);

        expect(domQuery($html, '//a')[0]->getAttribute('href'))->toBe('https://example.test/x');
    });
});
