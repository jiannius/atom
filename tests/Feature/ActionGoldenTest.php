<?php

// The byte-for-byte promise of the action engine (design rules, phase 5): a surface that holds no <atom:action> prints, byte for byte (whitespace included, bar the tail of the file), what it printed
// before the engine existed. The files under tests/Fixtures/golden were recorded from the surfaces BEFORE any of them was wired to read
// actions (the commit that adds them changes no component). A later change that alters the markup of a surface with no action fails here by
// name. To record a new case, run with GOLDEN_RECORD=1 and review the new file; never re-record an existing one to make this pass.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** Render Blade inside a Livewire component, so the `@if` markers a real page prints are compiled in. */
function goldenLivewire(string $blade): string
{
    $component = new class extends \Livewire\Component {
        use \Jiannius\Atom\Traits\WithTable;

        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    $html = \Livewire\Livewire::test($component::class)->html();

    // the component's root carries Livewire's snapshot and random ids; the rest is what a surface drew
    return preg_replace('/ wire:(?:key="lw-[^"]*"|snapshot="[^"]*"|effects="[^"]*"|id="[^"]*"|name="[^"]*")/', '', $html);
}

/** @return array<string, array{0: string, 1: bool}> name => [Blade, rendered inside a Livewire component] */
function goldenCases(): array
{
    $falses = '@if (false) <atom:menu.item>a</atom:menu.item> @endif @if (false) <atom:menu.item>b</atom:menu.item> @endif';
    $item = '<atom:menu.item>Edit</atom:menu.item>';
    $rows = '<x-slot:rows><atom:table.row><atom:table.cell>x</atom:table.cell></atom:table.row></x-slot:rows>';

    return [
        'header-full' => ['<atom:page.header title="INV-0042" :crumbs="[[\'label\' => \'Invoices\', \'href\' => \'/i\'], [\'label\' => \'INV-0042\']]" :facts="[[\'label\' => \'Due\', \'value\' => \'1 Dec\'], \'Open\']">
            <x-slot:status><atom:status tone="success" label="Done" /></x-slot:status>
            <x-slot:meta><span>meta</span></x-slot:meta>
            <x-slot:actions><atom:button id="a1">Edit</atom:button><atom:button id="a2">Print</atom:button></x-slot:actions>
            <x-slot:menu><atom:menu.item>Duplicate</atom:menu.item><atom:menu.separator /><atom:menu.item variant="warning">Void</atom:menu.item><atom:menu.item variant="delete">Delete</atom:menu.item></x-slot:menu>
            <x-slot:primary><atom:button variant="primary" id="p">Send</atom:button></x-slot:primary>
        </atom:page.header>', false],
        'header-small' => ['<atom:page.header title="Edit" size="sm" level="2"><x-slot:actions><atom:button size="sm">One</atom:button></x-slot:actions><x-slot:menu>'.$item.'</x-slot:menu></atom:page.header>', false],
        'header-title-only' => ['<atom:page.header title="Only a title" />', false],
        'header-menu-only' => ['<atom:page.header title="T"><x-slot:menu>'.$item.'</x-slot:menu></atom:page.header>', false],
        'header-default-slot' => ['<atom:page.header title="T"><p id="ignored">never printed</p></atom:page.header>', false],
        'header-false-blocks' => ['<atom:page.header title="T"><x-slot:actions>'.$falses.'</x-slot:actions><x-slot:menu>'.$falses.'</x-slot:menu><x-slot:primary>'.$falses.'</x-slot:primary></atom:page.header>', true],
        'header-blocks-and-buttons' => ['<atom:page.header title="T"><x-slot:actions>@if (true) <atom:button id="x">Yes</atom:button> @endif @if (false) <atom:button>No</atom:button> @endif</x-slot:actions></atom:page.header>', true],
        'toolbar-ends' => ['<atom:table.toolbar trashed>x<x-slot:menu><atom:menu.item>Archive all</atom:menu.item></x-slot:menu><x-slot:end><button id="e" type="button">End</button></x-slot:end><x-slot:actions><button id="a" type="button">Action</button></x-slot:actions></atom:table.toolbar>', false],
        'toolbar-start-only' => ['<atom:table.toolbar><atom:table.search /></atom:table.toolbar>', false],
        'toolbar-menu-only' => ['<atom:table.toolbar><x-slot:menu>'.$item.'</x-slot:menu></atom:table.toolbar>', false],
        'toolbar-false-blocks' => ['<atom:table.toolbar>x<x-slot:actions>'.$falses.'</x-slot:actions><x-slot:menu>'.$falses.'</x-slot:menu></atom:table.toolbar>', true],
        'bulk-checked' => ['<atom:table :empty="false"><x-slot:checked><atom:button id="a" size="sm">Send</atom:button><atom:button id="b" type="delete" size="sm">Delete</atom:button></x-slot:checked><x-slot:checked-menu>'.$item.'</x-slot:checked-menu>'.$rows.'</atom:table>', false],
        'bulk-accent' => ['<atom:table :empty="false"><x-slot:checked tone="accent" data-x="1"><b>bulk</b></x-slot:checked>'.$rows.'</atom:table>', false],
        'bulk-menu-only' => ['<atom:table :empty="false"><x-slot:checked-menu>'.$item.'</x-slot:checked-menu>'.$rows.'</atom:table>', false],
        'bulk-false-blocks' => ['<atom:table :empty="false"><x-slot:checked>'.$falses.'</x-slot:checked><x-slot:checked-menu>'.$falses.'</x-slot:checked-menu>'.$rows.'</atom:table>', true],
        'card-heading-only' => ['<atom:card heading="Customer">body</atom:card>', false],
        'card-actions-menu' => ['<atom:card heading="Parts"><x-slot:actions><atom:link href="/parts">View all</atom:link></x-slot:actions><x-slot:menu><atom:menu.item>Export</atom:menu.item></x-slot:menu>body</atom:card>', false],
        // whitespace after `</x-slot:menu>`, so the slot closes (the two cases above write `</x-slot:menu>body`, which never does): the ⋯ path, recorded from fd05d0e
        'card-menu-ws' => ['<atom:card heading="Parts"><x-slot:actions><atom:link href="/parts">View all</atom:link></x-slot:actions><x-slot:menu><atom:menu.item>Export</atom:menu.item></x-slot:menu>
body</atom:card>', false],
        'card-no-row' => ['<atom:card>body</atom:card>', false],
        'card-false-blocks' => ['<atom:card heading="T"><x-slot:actions>'.$falses.'</x-slot:actions><x-slot:menu>'.$falses.'</x-slot:menu>
body</atom:card>', true],
        'panel-actions-menu' => ['<atom:panel heading="Lines"><x-slot:actions><atom:link href="/l">All</atom:link></x-slot:actions><x-slot:menu><atom:menu.item>Export</atom:menu.item><atom:menu.separator /><atom:menu.item variant="delete">Clear</atom:menu.item></x-slot:menu>body</atom:panel>', false],
        'panel-menu-ws' => ['<atom:panel heading="Lines"><x-slot:actions><atom:link href="/l">All</atom:link></x-slot:actions><x-slot:menu><atom:menu.item>Export</atom:menu.item><atom:menu.separator /><atom:menu.item variant="delete">Clear</atom:menu.item></x-slot:menu>
body</atom:panel>', false],
        'panel-heading-only' => ['<atom:panel heading="Lines">body</atom:panel>', false],
        'row-actions' => ['<atom:table.actions>'.$item.'<atom:menu.separator /><atom:menu.item variant="delete">Delete</atom:menu.item></atom:table.actions>', false],
    ];
}

it('prints the same bytes as before the engine for every surface with no action', function (string $name) {
    [$blade, $livewire] = goldenCases()[$name];
    $html = $livewire ? goldenLivewire($blade) : renderBlade($blade);
    $file = __DIR__.'/../Fixtures/golden/'.$name.'.html';

    if (getenv('GOLDEN_RECORD') && ! is_file($file)) {
        file_put_contents($file, rtrim($html));
    }

    expect(is_file($file))->toBeTrue('no golden file for '.$name)
        ->and(rtrim($html))->toBe(rtrim(file_get_contents($file)));
})->with(fn () => array_keys(goldenCases()));

it('has a golden case for each of the five surfaces', function () {
    $names = implode(' ', array_keys(goldenCases()));

    foreach (['header', 'toolbar', 'bulk', 'card', 'panel', 'row'] as $surface) {
        expect($names)->toContain($surface.'-');
    }
});
