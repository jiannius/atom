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

describe('avatar', function () {
    it('is a tinted square with a hairline, on the neutral tone', function () {
        $classes = domClasses(displayElement('<atom:avatar name="Jane Roe" />', 'data-atom-avatar'));

        expect($classes)->toContain('bg-neutral-bg', 'text-neutral-fg', 'border', 'rounded-control', 'size-[34px]', 'font-semibold', 'text-sm')
            ->and(array_filter($classes, fn ($c) => str_starts_with($c, 'shadow')))->toBe([]);
    });

    it('has five sizes: 24, 28, 34, 48 and 64px, rounder as it grows', function (?string $size, string $box, string $radius) {
        $classes = domClasses(displayElement('<atom:avatar name="Jane Roe" :size="$size" />', 'data-atom-avatar', compact('size')));

        expect($classes)->toContain($box, $radius);
    })->with([
        ['xs', 'size-6', 'rounded-control'],
        ['sm', 'size-7', 'rounded-control'],
        [null, 'size-[34px]', 'rounded-control'],
        ['lg', 'size-12', 'rounded-panel'],
        ['xl', 'size-16', 'rounded-panel'],
    ]);

    it('is a circle when it is not square', function () {
        expect(domClasses(displayElement('<atom:avatar name="Jane Roe" :square="false" />', 'data-atom-avatar')))->toContain('rounded-full')->not->toContain('aspect-square');
    });

    it('rings an overlapping avatar in the surface colour, so a stack keeps its edges', function () {
        $html = renderBlade('<atom:avatar.group max="2"><atom:avatar name="Anna" /><atom:avatar name="Beth" /><atom:avatar name="Cara" /></atom:avatar.group>');
        $group = domQuery($html, '//body/*[1]')[0];

        expect(domClasses($group))->toContain('[&_[data-atom-avatar]]:ring-2', '[&_[data-atom-avatar]]:ring-surface')
            ->and($html)->toContain('+1');
    });

    it('prints a name as text', function () {
        $html = renderBlade('<atom:avatar :name="$n" :src="$s" />', ['n' => '"><script>x</script>', 's' => 'https://cdn.test/a.jpg']);

        expect(domQuery($html, '//script'))->toBeEmpty();
    });
});

describe('profile', function () {
    it('names the person in the foreground and the email in the muted token', function () {
        $html = renderBlade('<atom:profile name="Jane Roe" email="jane@test.com" />');

        expect(domClasses(domQuery($html, '//*[@data-atom-profile]/div[2]/div[1]')[0]))->toContain('text-fg')
            ->and(domClasses(domQuery($html, '//*[@data-atom-profile]/div[2]/div[2]')[0]))->toContain('text-xs', 'text-fg-muted');
    });
});

describe('progress', function () {
    it('is a pill track on the strong hairline token, 6px high and 4px at sm', function () {
        $default = renderBlade('<atom:progress :value="40" />');
        $small = renderBlade('<atom:progress :value="40" size="sm" />');

        expect(domClasses(domQuery($default, '//*[@role="progressbar"]')[0]))->toContain('bg-border-strong', 'rounded-pill', 'h-1.5')
            ->and(domClasses(domQuery($small, '//*[@role="progressbar"]')[0]))->toContain('h-1');
    });

    it('fills with the accent ink by default and with a tone\'s ink for a variant', function (?string $variant, string $fill) {
        $html = renderBlade('<atom:progress :value="40" :variant="$variant" />', compact('variant'));

        expect(domClasses(domQuery($html, '//*[@role="progressbar"]/div')[0]))->toContain($fill, 'rounded-pill');
    })->with([
        [null, 'bg-accent-ink'],
        ['success', 'bg-success-fg'],
        ['warning', 'bg-warning-fg'],
        ['danger', 'bg-danger-fg'],
        ['error', 'bg-danger-fg'],
        ['info', 'bg-info-fg'],
    ]);

    it('prints the label in the muted token with tabular figures', function () {
        $html = renderBlade('<atom:progress :value="40" label />');

        expect($html)->toContain('text-fg-muted', 'tabular-nums', '40%');
    });
});

describe('skeleton and placeholder bar', function () {
    it('pulses only for a viewer who has not asked for less motion, on the hairline token', function () {
        $html = renderBlade('<atom:skeleton />');

        expect($html)->toContain('motion-safe:animate-pulse', '*:bg-border-strong')->not->toMatch('/(?<!motion-safe:)animate-pulse/');
    });

    it('draws a placeholder bar as a pill on the hairline token, which a caller class replaces', function () {
        expect(domClasses(domQuery(renderBlade('<atom:placeholder-bar size="50%x8" />'), '//div')[0]))->toContain('rounded-pill', 'bg-border-strong');
        expect(domClasses(domQuery(renderBlade('<atom:placeholder-bar size="50%x8" class="bg-neutral-bg" />'), '//div')[0]))->toContain('bg-neutral-bg')->not->toContain('bg-border-strong');
    });
});

describe('kbd', function () {
    it('draws a key cap on the sunken fill with a strong hairline, 20px high', function () {
        $keys = domQuery(renderBlade('<atom:kbd keys="cmd k" />'), '//kbd');

        expect($keys)->toHaveCount(2);

        foreach ($keys as $key) {
            expect(domClasses($key))->toContain('h-5', 'min-w-5', 'rounded-nav', 'border', 'border-border-strong', 'bg-surface-sunken', 'text-fg-muted', 'text-xs');
        }
    });
});

describe('pagination', function () {
    $pager = fn (int $page = 3) => new \Illuminate\Pagination\LengthAwarePaginator(collect(range(1, 20)), 200, 20, $page);

    it('draws every cell as a control-high square: the control token, a 7px radius, tabular figures', function () use ($pager) {
        $html = renderBlade('<atom:pagination :paginator="$p" />', ['p' => $pager()]);

        foreach (domQuery($html, '//nav//button|//nav//*[@aria-current]') as $cell) {
            expect(domClasses($cell))->toContain('h-control', 'min-w-(--atom-control-h)', 'rounded-control', 'tabular-nums');
        }

        $buttons = domQuery($html, '//nav//button');

        expect($buttons)->not->toBeEmpty();

        foreach ($buttons as $button) {
            expect(domClasses($button))->toContain('h-control', 'rounded-control');
        }
    });

    it('marks the current page with aria-current, on a fill with a strong hairline, not with an underline alone', function () use ($pager) {
        $current = domQuery(renderBlade('<atom:pagination :paginator="$p" />', ['p' => $pager(3)]), '//*[@aria-current="page"]');

        expect($current)->toHaveCount(1)
            ->and(domClasses($current[0]))->toContain('bg-neutral-bg', 'text-fg', 'border', 'border-border-strong')
            ->and(trim($current[0]->textContent))->toBe('3');
    });

    it('dims a disabled end and keeps it out of the tab order', function () use ($pager) {
        $html = renderBlade('<atom:pagination :paginator="$p" />', ['p' => $pager(1)]);
        $off = domQuery($html, '//*[@aria-disabled="true"]//*[@aria-hidden="true"]');

        expect($off)->toHaveCount(1)
            ->and(domClasses($off[0]))->toContain('opacity-50', 'cursor-default', 'text-fg-subtle')
            ->and(domQuery($html, '//*[@aria-disabled="true"]//button'))->toBeEmpty();
    });

    it('draws a 16px arrow, labels it, and puts no :focus ring on a cell', function () use ($pager) {
        $html = renderBlade('<atom:pagination :paginator="$p" />', ['p' => $pager(3)]);
        $arrow = domQuery($html, '//nav//button[@aria-label]/svg')[0];

        expect($arrow->getAttribute('class'))->toBe('size-4')
            ->and($html)->not->toMatch('/focus:(?:ring|outline)/');
    });

    it('prints the summary in the muted token, 12px', function () use ($pager) {
        $summary = domQuery(renderBlade('<atom:pagination :paginator="$p" />', ['p' => $pager()]), '//nav/div[1]')[0];

        expect(domClasses($summary))->toContain('text-xs', 'text-fg-muted');
    });

    it('draws the table\'s pager the same way', function () use ($pager) {
        $html = renderBlade('<atom:table.pagination :paginate="$p" />', ['p' => $pager(2)]);

        expect(domClasses(domQuery($html, '//*[@aria-current="page"]')[0]))->toContain('bg-neutral-bg', 'h-control')
            ->and(domClasses(domQuery($html, '//nav/div[1]')[0]))->toContain('text-xs', 'text-fg-muted');
    });
});

describe('table, the rest of its parts', function () {
    it('draws the row checkbox as the 16px box of the checkbox control, on the accent ink when checked', function () {
        $classes = domClasses(displayElement('<atom:table.checkbox />', 'data-atom-table-checkbox'));

        expect($classes)->toContain('size-4', 'rounded', 'bg-surface', 'border-fg-subtle', 'data-[checked]:border-accent-ink', 'data-[checked]:bg-accent-ink', 'data-[checked]:*:opacity-100', '*:opacity-0');
    });

    it('draws a filter chip as a 26px pill on the neutral tone, with a muted name', function () {
        $html = renderBlade('<atom:table.filters>x</atom:table.filters>');
        $chip = domQuery($html, '//*[@data-atom-table-filter-chip]')[0];

        expect(domClasses($chip))->toContain('h-[26px]', 'rounded-pill', 'bg-neutral-bg', 'text-xs');
        expect($html)->toContain('text-fg-muted');
    });

    it('draws the card overflow row of the filters as a panel', function () {
        $html = renderBlade('<atom:table.filters overflow="card"><x-slot:more>m</x-slot:more>x</atom:table.filters>');

        expect($html)->toContain('rounded-panel border bg-surface');
    });

    it('draws the bulk bar in the muted token, its actions in the accent ink, and the loading spinner muted', function () {
        $html = renderBlade(
            '<atom:table :select-all="true" sticky-selection :paginate="$p"><x-slot:checked><button>Delete</button></x-slot:checked></atom:table>',
            ['p' => new \Illuminate\Pagination\LengthAwarePaginator([], 120, 50, 1)],
        );

        $bar = domQuery($html, '//*[@data-atom-table-checked]')[0];
        $barHtml = $bar->ownerDocument->saveHTML($bar);

        expect($barHtml)->toContain('text-fg-muted', 'text-accent-ink')
            ->not->toMatch('/\btext-(?:zinc|primary|muted)\b/')
            ->and($html)->toContain('size-6 text-fg-muted');
    });

    it('pulses the loading skeleton only for a viewer who has not asked for less motion', function () {
        $html = renderBlade('<atom:table skeleton>
            <x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns>
        </atom:table>');

        expect($html)->toContain('motion-safe:animate-pulse')->not->toMatch('/(?<!motion-safe:)animate-pulse/');
    });
});
