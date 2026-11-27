<?php

// <atom:input>'s `loading` slot (used by <atom:table.search>) and a Livewire render: an @if around a slot tag prints morph markers
// into the default slot of the component that receives it, which a `$slot->isNotEmpty()` check reads as content.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** Render Blade inside a Livewire component, so the `<!--[if BLOCK]-->` markers of an `@if` are compiled in */
function inputLoaderLivewire(string $blade): string
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

it('draws a colour input with its swatch inside a Livewire component', function () {
    $html = inputLoaderLivewire('<atom:input type="color" name="c" />');

    expect(domQuery($html, '//*[@data-atom-color-input]//input[@type="text"]'))->toHaveCount(1)
        ->and(domQuery($html, '//*[@data-atom-color-input]//*[@x-show="color"]'))->toHaveCount(1);
});

it('draws a tel input inside a Livewire component', function () {
    $html = inputLoaderLivewire('<atom:input type="tel" name="t" />');

    expect(domQuery($html, '//*[@data-atom-input-tel]//input'))->not->toBeEmpty();
});

it('prints the loading slot on a text input with its wire:loading.flex and no empty box without one', function () {
    $with = renderBlade('<atom:input name="q"><x-slot:loading wire:loading.flex wire:target="$refresh">spin</x-slot:loading></atom:input>');
    $without = inputLoaderLivewire('<atom:input name="q" />');

    $loader = domQuery($with, '//*[@data-atom-input-loading]');

    expect($loader)->toHaveCount(1)
        ->and($loader[0]->hasAttribute('wire:loading.flex'))->toBeTrue()
        ->and($loader[0]->getAttribute('wire:target'))->toBe('$refresh')
        ->and(domQuery($without, '//*[@data-atom-input-loading]'))->toHaveCount(0);
});
