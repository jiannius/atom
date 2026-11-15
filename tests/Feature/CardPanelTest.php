<?php

use Jiannius\Atom\Services\Docs;

// A card's heading, `actions` and `menu` slots (issue #209) and <atom:panel>: both draw the one private heading row
// (components/_heading-row.blade.php). The panel is the box a list lives in, so it clips and never hides its overflow.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** A false `@if` block, which prints Livewire's markers in a component and nothing otherwise */
const CARD_FALSE_BLOCKS = '@if (false) <a href="/x">a</a> @endif @if (false) <a href="/y">b</a> @endif';

/** Render Blade inside a Livewire component, so the `<!--[if BLOCK]-->` markers of an `@if` are compiled in */
function cardLivewire(string $blade): string
{
    $component = new class extends \Livewire\Component {
        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    return \Livewire\Livewire::test($component::class)->html();
}

/** Two nodes found by separate parses of the same markup are the same node when they sit at the same path */
function sameNode(DOMNode $a, DOMNode $b): bool
{
    return $a->getNodePath() === $b->getNodePath();
}

function headingRow(string $html): ?DOMElement
{
    return domQuery($html, '//*[@data-atom-heading-row]')[0] ?? null;
}

describe('card heading', function () {
    it('draws the heading prop in a heading element, 12px muted, level 2 by default', function () {
        $html = renderBlade('<atom:card heading="Customer">body</atom:card>');
        $title = domQuery($html, '//h2[@data-atom-heading-row-title]');

        expect($title)->toHaveCount(1)
            ->and(domClasses($title[0]))->toContain('text-xs', 'font-medium', 'text-fg-subtle')
            ->and($title[0]->textContent)->toBe('Customer')
            // 12px to the body, and the card's own 16px around it
            ->and(domClasses(headingRow($html)))->toContain('[:not(.grid,.flex,[class*=space-y-])>&]:mb-3')
            ->and(domClasses(domQuery($html, '//*[@data-atom-card]')[0]))->toContain('p-4');
    });

    it('takes a level of 2, 3 or 4, and an unknown one is 2', function () {
        foreach ([3 => 'h3', 4 => 'h4', 1 => 'h2', 5 => 'h2', 'x' => 'h2'] as $level => $tag) {
            $html = renderBlade('<atom:card heading="T" :level="$level">x</atom:card>', ['level' => $level]);

            expect(domQuery($html, '//'.$tag.'[@data-atom-heading-row-title]'))->toHaveCount(1);
        }
    });

    it('escapes the heading text, and takes markup through the slot', function () {
        $html = renderBlade('<atom:card :heading="$h">x</atom:card>', ['h' => '<script>alert(1)</script>']);

        expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');

        $slot = renderBlade('<atom:card>
            <x-slot:heading>
                Job <b>12</b>
            </x-slot:heading>
            x
        </atom:card>');

        expect(domQuery($slot, '//h2[@data-atom-heading-row-title]/b')[0]->textContent)->toBe('12');
    });

    it('draws no row without a heading, actions or a menu', function () {
        expect(headingRow(renderBlade('<atom:card>body</atom:card>')))->toBeNull()
            ->and(headingRow(renderBlade('<atom:card heading="">body</atom:card>')))->toBeNull()
            ->and(domQuery(renderBlade('<atom:card>body</atom:card>'), '//*[@data-atom-heading-row-title]'))->toBeEmpty();
    });

    it('puts the actions at the end of the row, 12px, and a menu behind the shared ⋯ trigger after them', function () {
        $html = renderBlade('<atom:card heading="Parts">
            <x-slot:actions>
                <atom:link href="/parts">View all</atom:link>
            </x-slot:actions>
            <x-slot:menu>
                <atom:menu.item>Export</atom:menu.item>
            </x-slot:menu>
            body
        </atom:card>');

        $group = domQuery($html, '//*[@data-atom-heading-row-actions]')[0];

        expect(domClasses($group))->toContain('text-xs')
            ->and(sameNode($group->parentNode, headingRow($html)))->toBeTrue()
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]/a[@href="/parts"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]//button[starts-with(@aria-label, "More actions")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]//*[@data-atom-menu]//*[@role="menuitem"]'))->toHaveCount(1)
            ->and(strpos($html, 'View all'))->toBeLessThan(strpos($html, 'More actions'))
            ->and(strpos($html, 'More actions'))->toBeLessThan(strpos($html, 'body'));
    });

    it('draws a row for actions or a menu alone', function () {
        $actions = renderBlade('<atom:card>
            <x-slot:actions>
                <atom:link href="/x">Edit</atom:link>
            </x-slot:actions>
            body
        </atom:card>');
        $menu = renderBlade('<atom:card>
            <x-slot:menu>
                <atom:menu.item>One</atom:menu.item>
            </x-slot:menu>
            body
        </atom:card>');

        expect(headingRow($actions))->not->toBeNull()
            ->and(domQuery($actions, '//*[@data-atom-heading-row-title]'))->toBeEmpty()
            ->and(domQuery($actions, '//button[@aria-label="More actions"]'))->toBeEmpty()
            ->and(headingRow($menu))->not->toBeNull()
            ->and(domQuery($menu, '//button[starts-with(@aria-label, "More actions")]'))->toHaveCount(1);
    });

    it('draws neither the row nor the trigger for slots of only false blocks, as a Livewire view prints them', function () {
        $html = cardLivewire('<atom:card>
            <x-slot:actions>'.CARD_FALSE_BLOCKS.'</x-slot:actions>
            <x-slot:menu>'.CARD_FALSE_BLOCKS.'</x-slot:menu>
            body
        </atom:card>');

        expect(headingRow($html))->toBeNull()
            ->and(domQuery($html, '//*[@data-atom-card]//button'))->toBeEmpty();

        $titled = cardLivewire('<atom:card heading="T">
            <x-slot:actions>'.CARD_FALSE_BLOCKS.'</x-slot:actions>
            <x-slot:menu>'.CARD_FALSE_BLOCKS.'</x-slot:menu>
            body
        </atom:card>');

        expect(domQuery($titled, '//*[@data-atom-heading-row-actions]'))->toBeEmpty()
            ->and(domQuery($titled, '//*[@data-atom-card]//button'))->toBeEmpty();
    });

    it('keeps the card\'s 16px on an inset card, and is the first row of a divided one', function () {
        $inset = renderBlade('<atom:card inset heading="T">
            <div>body</div>
        </atom:card>');
        $divided = renderBlade('<atom:card divided heading="T"><div>a</div><div>b</div></atom:card>');
        $both = renderBlade('<atom:card inset divided heading="T"><div>a</div></atom:card>');

        expect(domClasses(headingRow($inset)))->toContain('px-4', 'pt-4', '[:not(.grid,.flex,[class*=space-y-])>&]:pb-3')->not->toContain('[:not(.grid,.flex,[class*=space-y-])>&]:mb-3')
            // a divided card pads and rules every child div: the row is one, so it takes the same 16px and a hairline under it
            ->and(domClasses(headingRow($divided)))->not->toContain('[:not(.grid,.flex,[class*=space-y-])>&]:mb-3', 'p-4')
            ->and(domClasses(domQuery($divided, '//*[@data-atom-card]')[0]))->toContain('divide-y', '[&>div]:p-4')
            ->and(sameNode(domQuery($divided, '//*[@data-atom-card]/*')[0], headingRow($divided)))->toBeTrue()
            ->and(domClasses(headingRow($both)))->toContain('p-4');
    });

    it('lets the row\'s 12px yield to a card that lays its children out with its own gap, and to a space-y', function () {
        $classes = domClasses(headingRow(renderBlade('<atom:card heading="T" class="grid gap-3">body</atom:card>')));

        // the margin exists only under a parent that is not a grid, a flex or a space-y (a margin at no specificity ties with space-y and wins)
        expect($classes)->toContain('[:not(.grid,.flex,[class*=space-y-])>&]:mb-3')
            ->not->toContain('mb-3', '[:where(&)]:mb-3');
    });

    it('names the ⋯ for the box it belongs to, translated once, and plain when there is no heading', function () {
        $name = fn (string $html) => domQuery($html, '//button[@aria-label]')[0]->getAttribute('aria-label');

        expect($name(renderBlade('<atom:card heading="Customer"><x-slot:menu><atom:menu.item>One</atom:menu.item></x-slot:menu></atom:card>')))
            ->toBe('More actions for Customer')
            ->and($name(renderBlade('<atom:panel heading="Invoices"><x-slot:menu><atom:menu.item>One</atom:menu.item></x-slot:menu></atom:panel>')))
            ->toBe('More actions for Invoices')
            // a slot heading: its text, markup stripped
            ->and($name(renderBlade('<atom:card>
                <x-slot:heading>
                    Job <b>12</b>
                </x-slot:heading>
                <x-slot:menu>
                    <atom:menu.item>One</atom:menu.item>
                </x-slot:menu>
            </atom:card>')))->toBe('More actions for Job 12')
            // no heading: the plain name
            ->and($name(renderBlade('<atom:card><x-slot:menu><atom:menu.item>One</atom:menu.item></x-slot:menu></atom:card>')))
            ->toBe('More actions')
            // text, never markup
            ->and($name(renderBlade('<atom:card :heading="$h"><x-slot:menu><atom:menu.item>One</atom:menu.item></x-slot:menu></atom:card>', ['h' => '<i>x</i> "q"'])))
            ->toBe('More actions for <i>x</i> "q"');

        $translator = app('translator');
        $translator->setLoaded(['*' => ['*' => [$translator->getLocale() => ['More actions for :heading' => 'Lagi untuk :heading', 'Customer' => 'Pelanggan']]]]);

        try {
            expect($name(renderBlade('<atom:card heading="Customer"><x-slot:menu><atom:menu.item>One</atom:menu.item></x-slot:menu></atom:card>')))
                ->toBe('Lagi untuk Pelanggan');
        } finally {
            $translator->setLoaded([]);
        }
    });

    it('draws the row after a cover', function () {
        $html = renderBlade('<atom:card heading="T">
            <x-slot:cover>
                <img src="x">
            </x-slot:cover>
            body
        </atom:card>');
        $children = domQuery($html, '//*[@data-atom-card]/*');

        expect($children[0]->nodeName)->toBe('figure')
            ->and(sameNode($children[1], headingRow($html)))->toBeTrue();
    });

    it('leaves the stats and chart variants alone: their heading is their own label', function () {
        $stats = renderBlade('<atom:card variant="stats" heading="Sales" data="RM 1" />');
        $chart = renderBlade('<atom:card variant="chart" heading="Sales" :data="[]" />');

        expect(headingRow($stats))->toBeNull()
            ->and(headingRow($chart))->toBeNull()
            ->and($stats)->toContain('Sales')
            ->and($chart)->toContain('Sales');
    });
});

describe('panel', function () {
    it('is a clipping box, never overflow-hidden, with the panel radius, a hairline and the surface fill', function () {
        $html = renderBlade('<atom:panel>body</atom:panel>');
        $classes = domClasses(domQuery($html, '//*[@data-atom-panel]')[0]);

        expect($classes)->toContain('min-w-0', 'overflow-clip', 'rounded-panel', 'border', 'bg-surface')
            ->and($classes)->not->toContain('overflow-hidden', 'overflow-auto', 'overflow-x-auto', 'p-4')
            ->and($html)->not->toContain('overflow-hidden');
    });

    it('also clips in a rig without Tailwind (a rule on the hook in atom.css)', function () {
        expect(file_get_contents(__DIR__.'/../../resources/css/atom.css'))
            ->toMatch('/\[data-atom-panel\]\s*\{[^}]*overflow:\s*clip;/');
    });

    it('draws a heading row of the title, the count as text, then the actions and the ⋯ at the end, over a hairline', function () {
        $html = renderBlade('<atom:panel heading="Invoices" :count="128" level="3">
            <x-slot:actions>
                <atom:link href="/all">View all</atom:link>
            </x-slot:actions>
            <x-slot:menu>
                <atom:menu.item>Export</atom:menu.item>
            </x-slot:menu>
            body
        </atom:panel>');

        $title = domQuery($html, '//h3[@data-atom-heading-row-title]');
        $count = domQuery($html, '//*[@data-atom-heading-row-count]');

        expect(domClasses(headingRow($html)))->toContain('border-b', 'px-row', 'py-2.5')
            ->and($title)->toHaveCount(1)
            ->and(domClasses($title[0]))->toContain('text-sm', 'font-semibold')
            ->and($title[0]->textContent)->toBe('Invoices')
            ->and($count[0]->textContent)->toBe('128')
            ->and(domClasses($count[0]))->toContain('text-xs', 'tabular-nums', 'text-fg-subtle')
            // the count sits beside the heading, not in it: a screen reader does not read it as part of the title
            ->and(sameNode($count[0]->parentNode, $title[0]->parentNode))->toBeTrue()
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]/a[@href="/all"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-heading-row-actions]//button[starts-with(@aria-label, "More actions")]'))->toHaveCount(1);
    });

    it('prints a count of 0 and a string count as given', function () {
        expect(domQuery(renderBlade('<atom:panel heading="Jobs" :count="0">x</atom:panel>'), '//*[@data-atom-heading-row-count]')[0]->textContent)->toBe('0')
            ->and(domQuery(renderBlade('<atom:panel heading="Jobs" count="12 of 40">x</atom:panel>'), '//*[@data-atom-heading-row-count]')[0]->textContent)->toBe('12 of 40');
    });

    it('draws no row without a heading, actions or a menu, and the body is the slot, unpadded', function () {
        $html = renderBlade('<atom:panel><p>body</p></atom:panel>');
        $body = domQuery($html, '//*[@data-atom-panel-body]')[0];

        expect(headingRow($html))->toBeNull()
            ->and(domClasses($body))->not->toContain('px-row', 'py-3')
            ->and($body->textContent)->toContain('body')
            ->and(domQuery($html, '//*[@data-atom-panel-footer]'))->toBeEmpty();
    });

    it('pads the body for text with padded', function () {
        $body = domQuery(renderBlade('<atom:panel padded>text</atom:panel>'), '//*[@data-atom-panel-body]')[0];

        expect(domClasses($body))->toContain('px-row', 'py-3');
    });

    it('draws a footer under a hairline after the body, and none for a slot of only false blocks', function () {
        $html = renderBlade('<atom:panel heading="T">
            body
            <x-slot:footer>
                4 of 8 invoices
            </x-slot:footer>
        </atom:panel>');
        $footer = domQuery($html, '//*[@data-atom-panel-footer]')[0];

        expect(domClasses($footer))->toContain('border-t', 'px-row', 'py-2.5', 'text-xs', 'text-fg-subtle', 'tabular-nums', 'flex-wrap')
            ->and($footer->textContent)->toContain('4 of 8 invoices')
            ->and(sameNode(domQuery($html, '//*[@data-atom-panel]/*')[2], $footer))->toBeTrue();

        $empty = cardLivewire('<atom:panel heading="T">
            body
            <x-slot:footer>'.CARD_FALSE_BLOCKS.'</x-slot:footer>
        </atom:panel>');

        expect(domQuery($empty, '//*[@data-atom-panel-footer]'))->toBeEmpty();
    });

    it('draws no row for actions and a menu of only false blocks', function () {
        $html = cardLivewire('<atom:panel>
            <x-slot:actions>'.CARD_FALSE_BLOCKS.'</x-slot:actions>
            <x-slot:menu>'.CARD_FALSE_BLOCKS.'</x-slot:menu>
            body
        </atom:panel>');

        expect(headingRow($html))->toBeNull()
            ->and(domQuery($html, '//*[@data-atom-panel]//button'))->toBeEmpty();
    });

    it('escapes the heading and the count', function () {
        $html = renderBlade('<atom:panel :heading="$h" :count="$c">x</atom:panel>', ['h' => '<b>x</b>', 'c' => '<i>1</i>']);

        expect($html)->not->toContain('<b>x</b>')->not->toContain('<i>1</i>');
    });

    it('holds a frameless table, which stays framed on its own', function () {
        $panel = renderBlade('<atom:panel heading="Jobs"><atom:table frameless :empty="false"><x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns></atom:table></atom:panel>');
        $alone = renderBlade('<atom:table :empty="false"><x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns></atom:table>');

        expect(domQuery($panel, '//*[@data-atom-panel]//*[@data-atom-table]'))->not->toBeEmpty()
            ->and(domQuery($panel, '//*[@data-atom-table]/*[contains(@class, "rounded-panel")]'))->toBeEmpty()
            ->and(domQuery($alone, '//*[@data-atom-table]//*[contains(@class, "rounded-panel")]'))->not->toBeEmpty();
    });

    it('is listed under Display & typography, with its props', function () {
        $docs = new Docs;

        expect($docs->category('panel'))->toBe('Display & typography')
            ->and(collect($docs->component('panel')['props'])->pluck('name')->all())->toContain('heading', 'count', 'level', 'padded');
    });
});

describe('the heading row', function () {
    it('is private: it is not listed in the docs', function () {
        expect(collect((new Docs)->components())->pluck('name')->all())->not->toContain('_heading-row', 'heading-row');
    });

    it('uses the shared ⋯ trigger, a ghost sm button named "More actions"', function () {
        $html = renderBlade('<atom:panel>
            <x-slot:menu>
                <atom:menu.item>One</atom:menu.item>
            </x-slot:menu>
        </atom:panel>');
        $more = domQuery($html, '//button[@aria-label="More actions"]')[0];

        expect(domClasses($more))->toContain('bg-transparent', 'h-control-sm');
    });
});

describe('docs demos', function () {
    it('renders the card heading demo and every panel demo', function (string $demo) {
        expect(view('atom::docs.demos.'.$demo)->render())->toContain('data-atom-');
    })->with(['card.heading', 'panel.basic', 'panel.list', 'panel.footer']);
});
