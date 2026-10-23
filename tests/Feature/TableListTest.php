<?php

// V4-2c: the list parts of the table (frameless, numeric, the quiet header, the toolbar, saved views).

function tableHtml(string $attributes = '', string $rows = '<atom:table.row><atom:table.cell>Ana</atom:table.cell></atom:table.row>'): string
{
    return renderBlade('<atom:table :empty="false" '.$attributes.'>
        <x-slot:columns><atom:table.column>Name</atom:table.column></x-slot:columns>
        <x-slot:rows>'.$rows.'</x-slot:rows>
    </atom:table>');
}

describe('frameless', function () {
    it('draws the table in its own panel by default', function () {
        $html = tableHtml();
        $frame = domQuery($html, '//*[@data-atom-table]/div/div[contains(@class, "overflow-hidden")]');

        expect($frame)->toHaveCount(1)
            ->and(domClasses($frame[0]))->toContain('border', 'bg-surface', 'rounded-panel')
            ->and(domQuery($html, '//*[@data-atom-table-frameless]'))->toBeEmpty();
    });

    it('drops the frame classes and keeps the hairlines', function () {
        $html = tableHtml('frameless');
        $root = domQuery($html, '//*[@data-atom-table-frameless]');

        expect($root)->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-table]//*[contains(@class, "rounded-panel")]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-table]//div[contains(concat(" ", normalize-space(@class), " "), " bg-surface ")]'))->toBeEmpty()
            ->and(domClasses($root[0]))->not->toContain('space-y-4')
            ->and(domQuery($html, '//*[@data-atom-table]/div/div[contains(@class, "divide-y")]'))->toHaveCount(1)
            ->and(domQuery($html, '//table[contains(@class, "divide-y")]'))->toHaveCount(1);
    });

    it('turns the header and the bulk bar into hairline strips', function () {
        $html = renderBlade('<atom:table :empty="false" frameless>
            <x-slot:header><span id="h">controls</span></x-slot:header>
            <x-slot:checked><span id="c">bulk</span></x-slot:checked>
            <x-slot:rows><atom:table.row><atom:table.cell>x</atom:table.cell></atom:table.row></x-slot:rows>
        </atom:table>');

        $checked = domQuery($html, '//*[@data-atom-table-checked]');

        expect($checked)->toHaveCount(1)
            ->and(domClasses($checked[0]))->toContain('border-b', 'px-3')
            ->and($html)->toContain('border-b px-3 py-2.5');
    });

    it('lets the table shrink inside a grid or flex parent', function () {
        $root = domQuery(tableHtml('frameless'), '//*[@data-atom-table]')[0];

        expect(domClasses($root))->toContain('min-w-0', 'max-w-full');
    });

    it('keeps the loading veil square when there is no frame', function () {
        $veil = domQuery(tableHtml('frameless'), '//*[@data-atom-table-loading]')[0];
        $framed = domQuery(tableHtml(), '//*[@data-atom-table-loading]')[0];

        expect(domClasses($veil))->not->toContain('rounded-panel')
            ->and(domClasses($framed))->toContain('rounded-panel');
    });
});

describe('numeric', function () {
    it('right-aligns a numeric cell with tabular figures', function () {
        $cell = domQuery(renderBlade('<atom:table.cell numeric>1,280.00</atom:table.cell>'), '//td')[0];

        expect(domClasses($cell))->toContain('text-right', 'tabular-nums');
    });

    it('does not make an ordinary cell tabular', function () {
        $cell = domQuery(renderBlade('<atom:table.cell>Ana</atom:table.cell>'), '//td')[0];

        expect(domClasses($cell))->not->toContain('tabular-nums', 'text-right');
    });

    it('right-aligns a numeric header too, the label and the sort arrow', function () {
        $html = renderBlade('<atom:table.column numeric sort="amount">Amount (RM)</atom:table.column>');
        $th = domQuery($html, '//th')[0];

        expect(domClasses($th))->toContain('text-right')
            ->and(domClasses(domQuery($html, '//th/div')[0]))->toContain('justify-end');
    });

    it('wins over an align passed beside it', function () {
        $html = renderBlade('<atom:table.cell numeric align="left">1</atom:table.cell>');

        expect(domClasses(domQuery($html, '//td')[0]))->toContain('text-right')->not->toContain('text-left');
    });
});

describe('cells and headers', function () {
    it('keeps vertical padding and a floor height, never a fixed height with none', function () {
        $cell = domQuery(renderBlade('<atom:table.cell>x</atom:table.cell>'), '//td')[0];
        $classes = domClasses($cell);

        expect($classes)->toContain('px-row', 'py-row', 'leading-row', 'h-(--atom-row-min-h)')
            ->and($classes)->not->toContain('py-0', 'p-0');
    });

    it('prints the header as a quiet 12px label, not an uppercase one', function () {
        $html = renderBlade('<atom:table.column>Customer</atom:table.column>');
        $label = domQuery($html, '//th/div')[0];

        expect(domClasses($label))->toContain('text-xs', 'font-medium', 'text-fg-subtle')
            ->and($html)->not->toContain('uppercase')->not->toContain('tracking-wider');
    });

    it('uses the same horizontal padding in a header and a cell, so the two line up', function () {
        $header = domQuery(renderBlade('<atom:table.column>x</atom:table.column>'), '//th/div')[0];
        $cell = domQuery(renderBlade('<atom:table.cell>x</atom:table.cell>'), '//td')[0];

        expect(domClasses($header))->toContain('px-row')->and(domClasses($cell))->toContain('px-row');
    });
});

describe('toolbar on a phone', function () {
    it('gives the start group the full row, unless an end group (or trashed) shares it', function () {
        $alone = domClasses(domQuery(renderBlade('<atom:table.toolbar>x</atom:table.toolbar>'), '//*[@data-atom-table-toolbar-start]')[0]);
        $shared = domClasses(domQuery(renderBlade('<atom:table.toolbar>x<x-slot:end><button>E</button></x-slot:end></atom:table.toolbar>'), '//*[@data-atom-table-toolbar-start]')[0]);
        $trashed = domClasses(domQuery(renderBlade('<atom:table.toolbar trashed>x</atom:table.toolbar>'), '//*[@data-atom-table-toolbar-start]')[0]);

        expect($alone)->toContain('basis-full')->not->toContain('basis-0')
            ->and($shared)->toContain('basis-0')->not->toContain('basis-full')
            ->and($trashed)->toContain('basis-0')->not->toContain('basis-full');
    });
});

describe('toolbar', function () {
    it('lays the controls out, with the end group on the far side', function () {
        $html = renderBlade('<atom:table.toolbar><input id="q"><x-slot:end><button id="n" type="button">Export</button></x-slot:end></atom:table.toolbar>');

        expect(domQuery($html, '//*[@data-atom-table-toolbar]//*[@data-atom-table-toolbar-start]/input[@id="q"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-table-toolbar]//*[@data-atom-table-toolbar-end]/button[@id="n"]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//*[@data-atom-table-toolbar-end]')[0]))->toContain('ms-auto');
    });

    it('has no end group when there is nothing for it', function () {
        expect(domQuery(renderBlade('<atom:table.toolbar>x</atom:table.toolbar>'), '//*[@data-atom-table-toolbar-end]'))->toBeEmpty();
    });

    it('adds the archived toggle through trashed, and the voided one by name', function (mixed $value, string $label) {
        $html = renderBlade('<atom:table.toolbar :trashed="$t">x</atom:table.toolbar>', ['t' => $value]);

        expect(domQuery($html, '//*[@data-atom-table-toolbar-end]//*[@data-atom-table-trashed]'))->toHaveCount(1)
            ->and($html)->toContain($label);
    })->with([[true, 'Show archived'], ['voided', 'Show voided']]);

    it('sits in the table header, which the bulk bar replaces', function () {
        $html = renderBlade('<atom:table :empty="false" frameless>
            <x-slot:header><atom:table.toolbar><span id="s">search</span></atom:table.toolbar></x-slot:header>
            <x-slot:checked><span>bulk</span></x-slot:checked>
            <x-slot:rows><atom:table.row><atom:table.cell>x</atom:table.cell></atom:table.row></x-slot:rows>
        </atom:table>');

        // both bars are templates: the header is shown while nothing is ticked, the checked bar while something is
        expect($html)->toContain('x-if="!$wire._table.checkboxes.length"')
            ->and($html)->toContain('x-if="$wire._table.checkboxes.length || $wire._table.select_all"')
            ->and(preg_match('/<template x-if="!\$wire\._table\.checkboxes\.length"[^>]*>.*data-atom-table-toolbar.*<\/template>/s', $html))->toBe(1);
    });
});

describe('saved views', function () {
    function viewsHtml(string $attributes = '', array $data = []): string
    {
        return renderBlade('<atom:table.views '.$attributes.'>
            <atom:table.views.item value="all" label="All" />
            <atom:table.views.item value="unpaid">Unpaid</atom:table.views.item>
            <atom:table.views.item value="paid" label="Paid" count="9" />
        </atom:table.views>', $data);
    }

    it('prints one button per view, named by its label or slot', function () {
        $html = viewsHtml(':counts="[\'all\' => 16, \'unpaid\' => 7]"');
        $items = domQuery($html, '//nav[@data-atom-table-views]/button[@data-atom-table-view]');

        expect($items)->toHaveCount(3)
            ->and(array_map(fn ($i) => $i->getAttribute('data-value'), $items))->toBe(['all', 'unpaid', 'paid'])
            ->and(trim(preg_replace('/\s+/', ' ', $items[1]->textContent)))->toBe('Unpaid 7')
            ->and($items[0]->getAttribute('type'))->toBe('button');
    });

    it('binds to the wire:model property, so WithUrlFilters can carry it', function () {
        $html = viewsHtml('wire:model.live="filters.view"');
        $nav = domQuery($html, '//nav[@data-atom-table-views]')[0];

        expect($nav->getAttribute('wire:model.live'))->toBe('filters.view')
            ->and($nav->getAttribute('x-modelable'))->toBe('value')
            ->and($nav->getAttribute('x-data'))->toContain("\$wire.\$get('filters.view')")
            ->and(domQuery($html, '//button[@data-value="unpaid"]')[0]->getAttribute('x-on:click'))->toBe("value = 'unpaid'");
    });

    it('is not modelable without a binding, and starts on `current`', function () {
        $html = viewsHtml('current="unpaid"');
        $nav = domQuery($html, '//nav[@data-atom-table-views]')[0];

        expect($nav->hasAttribute('x-modelable'))->toBeFalse()
            ->and($nav->getAttribute('x-data'))->toContain("'unpaid'")
            ->and(domQuery($html, '//button[@data-value="unpaid"]')[0]->getAttribute('aria-pressed'))->toBe('true')
            ->and(domQuery($html, '//button[@data-value="all"]')[0]->getAttribute('aria-pressed'))->toBe('false');
    });

    it('takes the counts from an array or a closure, and an item count wins', function () {
        $fromArray = viewsHtml(':counts="[\'all\' => 1234, \'paid\' => 1]"');
        $fromClosure = viewsHtml(':counts="fn ($value) => strlen($value)"');

        $count = fn (string $html, string $value) => trim(domQuery($html, '//button[@data-value="'.$value.'"]//*[@data-atom-table-view-count]')[0]->textContent ?? '');

        expect($count($fromArray, 'all'))->toBe('1,234')
            ->and($count($fromArray, 'paid'))->toBe('9')
            ->and($count($fromClosure, 'all'))->toBe('3')
            ->and($count($fromClosure, 'unpaid'))->toBe('6');
    });

    it('keeps the count of a value with a dot or a star: a key, not a path or a wildcard', function () {
        $html = renderBlade('<atom:table.views :counts="$counts">
            <atom:table.views.item value="q3.2026" label="Q3" />
            <atom:table.views.item value="a*" label="A" />
            <atom:table.views.item value="2026" label="Year" />
        </atom:table.views>', ['counts' => ['q3.2026' => 7, 'a*' => 4, 2026 => 12]]);

        $count = fn (string $value) => trim(domQuery($html, '//*[@data-value="'.$value.'"]//*[@data-atom-table-view-count]')[0]->textContent ?? '');

        expect($count('q3.2026'))->toBe('7')->and($count('a*'))->toBe('4')->and($count('2026'))->toBe('12');
    });

    it('takes counts from an ArrayAccess, and none from a value that is not a list', function () {
        $viaObject = renderBlade('<atom:table.views :counts="$counts"><atom:table.views.item value="a" label="A" /></atom:table.views>', ['counts' => new ArrayObject(['a' => 3])]);
        $viaString = renderBlade('<atom:table.views :counts="$counts"><atom:table.views.item value="a" label="A" /></atom:table.views>', ['counts' => 'nope']);

        expect(domQuery($viaObject, '//*[@data-atom-table-view-count]')[0]->textContent)->toBe('3')
            ->and(domQuery($viaString, '//*[@data-atom-table-view-count]'))->toBeEmpty();
    });

    it('compares the chosen value as text, so an integer property still marks its view', function () {
        $html = renderBlade('<atom:table.views wire:model.live="stage"><atom:table.views.item value="2" label="Two" /></atom:table.views>');

        expect(domQuery($html, '//button')[0]->getAttribute('x-bind:aria-pressed'))->toBe("String(value) === '2' ? 'true' : 'false'");
    });

    it('tells the table when the view changes, so a bulk selection is dropped', function () {
        $nav = domQuery(viewsHtml('wire:model.live="filters.view"'), '//nav')[0];

        expect($nav->getAttribute('x-data'))->toContain("\$watch('value', () => this.\$dispatch('table-filter:changed'))");
    });

    it('prints no count for a view the host gave none, and a zero for a view with none', function () {
        $html = viewsHtml(':counts="[\'unpaid\' => 0]"');

        expect(domQuery($html, '//button[@data-value="all"]//*[@data-atom-table-view-count]'))->toBeEmpty()
            ->and(domQuery($html, '//button[@data-value="unpaid"]//*[@data-atom-table-view-count]')[0]->textContent)->toBe('0');
    });

    it('never runs a query itself: the counts are what the host passed', function () {
        $calls = 0;
        viewsHtml(':counts="$counts"', ['counts' => function ($value) use (&$calls) {
            $calls++;

            return 5;
        }]);

        // one call per item that has no count of its own: atom adds none
        expect($calls)->toBe(2);
    });

    it('escapes the counts and the label', function () {
        $evil = '<img src=x onerror=alert(1)>';
        $html = renderBlade('<atom:table.views :counts="[\'a\' => $evil]"><atom:table.views.item value="a" :label="$evil" /></atom:table.views>', ['evil' => $evil]);

        expect(domQuery($html, '//nav//img'))->toBeEmpty()
            ->and(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'))->toBe(2);
    });

    it('prints a value as text in its attributes', function () {
        $html = renderBlade('<atom:table.views><atom:table.views.item :value="$v" label="x" /></atom:table.views>', ['v' => '"><script>alert(1)</script>']);

        expect(domQuery($html, '//script'))->toBeEmpty();
    });

    it('turns an item into a link with a safe href, and refuses an unsafe one', function () {
        $html = renderBlade('<atom:table.views>
            <atom:table.views.item value="a" label="A" href="?view=a" />
            <atom:table.views.item value="b" label="B" href="javascript:alert(1)" />
        </atom:table.views>');

        $a = domQuery($html, '//*[@data-value="a"]')[0];
        $b = domQuery($html, '//*[@data-value="b"]')[0];

        expect($a->nodeName)->toBe('a')->and($a->getAttribute('href'))->toBe('?view=a')
            ->and($b->nodeName)->toBe('button')->and($b->hasAttribute('href'))->toBeFalse();
    });

    it('is a named navigation landmark', function () {
        expect(domQuery(viewsHtml(), '//nav[@aria-label="Saved views"]'))->toHaveCount(1)
            ->and(domQuery(viewsHtml('label="Invoice views"'), '//nav[@aria-label="Invoice views"]'))->toHaveCount(1);
    });

    it('keeps the strip on one scrolling line', function () {
        expect(domClasses(domQuery(viewsHtml(), '//nav')[0]))->toContain('overflow-x-auto', 'border-b');
        expect(domClasses(domQuery(viewsHtml(), '//button')[0]))->toContain('whitespace-nowrap');
    });
});
