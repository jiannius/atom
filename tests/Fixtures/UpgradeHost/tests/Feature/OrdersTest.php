<?php

use Jiannius\Atom\Traits\AtomComponent;

it('renders', function () {
    $html = render();

    expect($html)->toContain('bg-white')->toContain('rounded-lg');
    expect($order->placed_at->pretty())->toBe('x');
    expect($page->find('dialog button'))->not->toBeNull();
});

class FixtureComponent extends Livewire\Component
{
    use AtomComponent;
}

it('draws the heading', function () {
    expect($page->find('h1.font-medium'))->not->toBeNull();
    expect($page->find('.subheading.font-medium'))->not->toBeNull();
});

it('weighs the heading', function () {
    expect(headingHtml('lg'))->toContain('font-semibold')
        ->and(headingHtml('sm'))->not->toContain('font-semibold');
    expect($html)->not->toContain('font-semibold');
});
