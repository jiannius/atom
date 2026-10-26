<?php

use Illuminate\View\ComponentSlot;
use Livewire\Component;
use Livewire\Livewire;

// A slot made of `@if` blocks that all come out false is not empty to the template: in a Livewire view each block still prints
// `<!--[if BLOCK]><![endif]-->` markers, and ComponentSlot::isNotEmpty() takes them for content. A component that draws a wrapper, a
// button or a divider because "the slot has content" then draws it around nothing (page.header's empty ⋯ button, a toolbar end group,
// a callout's actions row). They ask hasActualContent() instead, which ignores comments and whitespace.

/** A Livewire component that renders the given Blade, so the real morph markers are compiled in. */
function renderInLivewire(string $blade): string
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

/** A slot whose only content is `@if` blocks that are all false. */
const FALSE_BLOCKS = '@if (false) <button>a</button> @endif @if (false) <button>b</button> @endif';

it('really does print the markers, and ComponentSlot sees them as content (the premise)', function () {
    $slot = new ComponentSlot('<!--[if BLOCK]><![endif]--><!--[if ENDBLOCK]><![endif]-->');

    expect($slot->isNotEmpty())->toBeTrue()
        ->and($slot->hasActualContent())->toBeFalse()
        // the markers are in the compiled view (they are in the page, outside the header's slots)
        ->and(renderInLivewire('@if (false) x @endif'))->toContain('<!--[if BLOCK]><![endif]-->');
});

describe('page.header', function () {
    it('draws no actions wrapper and no ⋯ button for slots of only false blocks', function () {
        $html = renderInLivewire('<atom:page.header title="T">
            <x-slot:actions>'.FALSE_BLOCKS.'</x-slot:actions>
            <x-slot:menu>'.FALSE_BLOCKS.'</x-slot:menu>
            <x-slot:primary>'.FALSE_BLOCKS.'</x-slot:primary>
        </atom:page.header>');

        expect(domQuery($html, '//*[@data-atom-page-actions]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-page-header]//button'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-page-header]//*[@data-atom-menu]'))->toBeEmpty();
    });

    it('draws neither the status nor the meta row for slots of only false blocks', function () {
        $html = renderInLivewire('<atom:page.header>
            <x-slot:status>'.FALSE_BLOCKS.'</x-slot:status>
            <x-slot:meta>'.FALSE_BLOCKS.'</x-slot:meta>
        </atom:page.header>');

        expect(domQuery($html, '//*[@data-atom-page-meta]'))->toBeEmpty()
            // no title and no status: no title row either
            ->and(domQuery($html, '//*[@data-atom-page-header]/div/div[contains(@class, "items-center")]'))->toBeEmpty();
    });

    it('still draws a slot that has a real block, and one that has only whitespace around it is empty', function () {
        $real = renderInLivewire('<atom:page.header title="T"><x-slot:menu>@if (true) <atom:menu.item>Archive</atom:menu.item> @endif</x-slot:menu></atom:page.header>');
        $blank = renderInLivewire('<atom:page.header title="T"><x-slot:actions>   </x-slot:actions></atom:page.header>');

        expect(domQuery($real, '//*[@data-atom-page-actions]//button[@aria-label="More actions"]'))->toHaveCount(1)
            ->and(domQuery($blank, '//*[@data-atom-page-actions]'))->toBeEmpty();
    });
});

it('draws no end group for a toolbar whose end slot is only false blocks (and no count)', function () {
    $html = renderInLivewire('<atom:table.toolbar :count="false">x<x-slot:end>'.FALSE_BLOCKS.'</x-slot:end></atom:table.toolbar>');

    expect(domQuery($html, '//*[@data-atom-table-toolbar-end]'))->toBeEmpty()
        // so the start group keeps the full row on a phone
        ->and(domClasses(domQuery($html, '//*[@data-atom-table-toolbar-start]')[0]))->toContain('basis-full');
});

it('draws no actions row for a callout whose actions slot is only false blocks', function () {
    $with = renderInLivewire('<atom:callout>Text<x-slot:actions><button>Go</button></x-slot:actions></atom:callout>');
    $without = renderInLivewire('<atom:callout>Text<x-slot:actions>'.FALSE_BLOCKS.'</x-slot:actions></atom:callout>');

    expect(domQuery($with, '//button'))->toHaveCount(1)
        ->and(domQuery($without, '//button'))->toBeEmpty()
        ->and(strlen($without))->toBeLessThan(strlen($with));
});

it('draws no footer for a form section whose footer slot is only false blocks', function () {
    $with = renderInLivewire('<atom:form.section heading="H"><div>f</div><x-slot:footer><button>Save</button></x-slot:footer></atom:form.section>');
    $without = renderInLivewire('<atom:form.section heading="H"><div>f</div><x-slot:footer>'.FALSE_BLOCKS.'</x-slot:footer></atom:form.section>');

    expect(domQuery($with, '//button'))->toHaveCount(1)
        ->and(strlen($without))->toBeLessThan(strlen($with))
        ->and(domQuery($without, '//button'))->toBeEmpty();
});

it('draws no start group for a form action bar whose start slot is only false blocks', function () {
    $without = renderInLivewire('<atom:form.actions bar><x-slot:start>'.FALSE_BLOCKS.'</x-slot:start><button>Save</button></atom:form.actions>');

    expect(domQuery($without, '//*[@data-atom-form-actions-start]'))->toBeEmpty();
});

it('draws no actions beside a label whose actions slot is only false blocks', function () {
    $with = renderInLivewire('<atom:label for="x">Name<x-slot:actions><button>Edit</button></x-slot:actions></atom:label>');
    $without = renderInLivewire('<atom:label for="x">Name<x-slot:actions>'.FALSE_BLOCKS.'</x-slot:actions></atom:label>');

    expect(domQuery($with, '//button'))->toHaveCount(1)
        ->and(domQuery($without, '//button'))->toBeEmpty()
        ->and(strlen($without))->toBeLessThan(strlen($with));
});

it('draws no divider and actions row in a select list whose actions slot is only false blocks', function (string $variant) {
    $with = renderInLivewire('<atom:select.'.$variant.' :options="[]"><x-slot:actions><button>Add</button></x-slot:actions></atom:select.'.$variant.'>');
    $without = renderInLivewire('<atom:select.'.$variant.' :options="[]"><x-slot:actions>'.FALSE_BLOCKS.'</x-slot:actions></atom:select.'.$variant.'>');

    expect(domQuery($with, '//div[contains(@class, "border-t border-border mt-1")]//button[normalize-space()="Add"]'))->toHaveCount(1)
        ->and(domQuery($without, '//div[contains(@class, "border-t border-border mt-1")]'))->toBeEmpty();
})->with(['filter', 'listbox']);

it('draws no input actions for an input whose actions slot is only false blocks', function () {
    $with = renderInLivewire('<atom:input name="n"><x-slot:actions><button>Go</button></x-slot:actions></atom:input>');
    $without = renderInLivewire('<atom:input name="n"><x-slot:actions>'.FALSE_BLOCKS.'</x-slot:actions></atom:input>');

    expect(domQuery($with, '//button'))->toHaveCount(1)
        ->and(strlen($without))->toBeLessThan(strlen($with));
});

it('draws no shell bar for a sidebar layout whose crumbs and actions slots are only morph markers', function () {
    // the layout is a whole document, which a Livewire test cannot mount: the markers a view prints are written out
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $markers = '<!--[if BLOCK]><![endif]--><!--[if ENDBLOCK]><![endif]-->';
    $html = renderBlade('<atom:layouts.sidebar title="T" :vite="false"><x-slot:crumbs>'.$markers.'</x-slot:crumbs><x-slot:actions>'.$markers.'</x-slot:actions>body</atom:layouts.sidebar>');

    expect(domQuery($html, '//header[@data-atom-header-empty]'))->toHaveCount(1);
});
