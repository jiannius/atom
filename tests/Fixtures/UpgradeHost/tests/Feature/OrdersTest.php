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
