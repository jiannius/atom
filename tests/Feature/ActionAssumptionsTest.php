<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

// The three facts the action engine (design rules, phase 5) stands on, pinned so a Laravel change that breaks one fails here by name
// instead of as a surface that quietly stops placing actions. Each is read in Laravel's own vendor code; these prove it on the
// installed version.

it('renders a component\'s slot before the component\'s own view runs', function () {
    Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');

    $GLOBALS['atomActionProbe'] = [];

    renderBlade('<x-action-order-probe>@php($GLOBALS[\'atomActionProbe\'][] = \'slot\')</x-action-order-probe>');

    expect($GLOBALS['atomActionProbe'])->toBe(['slot', 'view']);

    unset($GLOBALS['atomActionProbe']);
});

it('counts a slot that holds only comments as empty, markers and Livewire block markers included', function () {
    $marker = '<!--atom:action:'.str_repeat('a', 32).'-->';

    expect((new ComponentSlot($marker))->hasActualContent())->toBeFalse()
        ->and((new ComponentSlot("\n  {$marker}\n {$marker}"))->hasActualContent())->toBeFalse()
        ->and((new ComponentSlot('<!--[if BLOCK]><![endif]-->'.$marker.'<!--[if ENDBLOCK]><![endif]-->'))->hasActualContent())->toBeFalse()
        ->and((new ComponentSlot($marker.'<b>x</b>'))->hasActualContent())->toBeTrue()
        ->and((new ComponentSlot($marker.' text'))->hasActualContent())->toBeTrue();
});

it('hands an attribute bag to a component\'s props with `:attributes`, once, and a prop written on the tag wins', function () {
    $bag = new ComponentAttributeBag(['variant' => 'ghost', 'wire:click' => 'go', 'class' => 'extra', 'x-on:click' => 'ok']);

    $fromBag = renderBlade('<atom:button :attributes="$bag">Hi</atom:button>', ['bag' => $bag]);
    $written = renderBlade('<atom:button variant="primary" :attributes="$bag">Hi</atom:button>', ['bag' => $bag]);

    $ghost = domQuery($fromBag, '//button')[0];
    $primary = domQuery($written, '//button')[0];

    expect(domClasses($ghost))->toContain('bg-transparent', 'extra')
        ->and($ghost->hasAttribute('variant'))->toBeFalse()
        ->and($ghost->getAttribute('wire:click'))->toBe('go')
        ->and($ghost->getAttribute('x-on:click'))->toBe('ok')
        ->and(substr_count($fromBag, 'wire:click="go"'))->toBe(1)
        ->and(domClasses($primary))->toContain('bg-accent')->not->toContain('bg-transparent')
        ->and(substr_count($written, 'wire:click="go"'))->toBe(1);
});
