<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * Props a component declares must reach the markup, and a typo in a utility class must not ship.
 */

it('keeps a host step on a number input and defaults to any', function () {
    $own = domQuery(renderBlade('<atom:input type="number" step="0.01" />'), '//input');
    expect($own[0]->getAttribute('step'))->toBe('0.01');

    $default = domQuery(renderBlade('<atom:input type="number" />'), '//input');
    expect($default[0]->getAttribute('step'))->toBe('any');

    $text = domQuery(renderBlade('<atom:input type="text" />'), '//input');
    expect($text[0]->hasAttribute('step'))->toBeFalse();
});

it('spells the card cover transition utility right', function () {
    $html = renderBlade('<atom:card><x-slot:cover><img src="/x.png" alt=""></x-slot:cover>Body</atom:card>');

    expect($html)->toContain('transition-transform')->not->toContain('transistion');
});

it('renders any dd group column count', function (string|int $cols, string $class) {
    $group = domQuery(renderBlade('<atom:dd.group cols="'.$cols.'">x</atom:dd.group>'), '//*[@data-atom-dd-group]');

    expect(domClasses($group[0]))->toContain($class);
})->with([
    'one' => [1, 'space-y-3'],
    'two' => [2, 'md:grid-cols-2'],
    'three' => [3, 'md:grid-cols-3'],
    'four' => [4, 'md:grid-cols-4'],
    'unknown' => [9, 'space-y-3'],
]);

it('prints the scripts of atom:html', function () {
    $html = renderBlade('<atom:html :vite="false" :fonts="false" :scripts="[\'/js/a.js\', \'https://cdn.example/b.js\', \'javascript:alert(1)\']">Hi</atom:html>');

    $scripts = collect(domQuery($html, '//script[@src]'))->map(fn (DOMElement $el) => $el->getAttribute('src'));

    expect($scripts->all())->toContain('/js/a.js', 'https://cdn.example/b.js')
        ->and($html)->not->toContain('javascript:alert');
});

it('forwards required to the checkbox and toggle input', function (string $tag) {
    $required = domQuery(renderBlade('<atom:'.$tag.' name="a" required label="A" />'), '//input[@type="checkbox"]');
    expect($required[0]->hasAttribute('required'))->toBeTrue();

    $bound = domQuery(renderBlade('<atom:'.$tag.' name="a" :required="true" label="A" />'), '//input[@type="checkbox"]');
    expect($bound[0]->hasAttribute('required'))->toBeTrue();

    $plain = domQuery(renderBlade('<atom:'.$tag.' name="a" label="A" />'), '//input[@type="checkbox"]');
    expect($plain[0]->hasAttribute('required'))->toBeFalse();
})->with(['checkbox', 'toggle']);

it('prints the icon of a badge in the plain and hex branches', function (string $attributes) {
    $html = renderBlade('<atom:badge icon="add" '.$attributes.'>New</atom:badge>');

    $badge = domQuery($html, '//*[@data-atom-badge]');
    expect($badge)->toHaveCount(1)
        ->and(domQuery($html, '//*[@data-atom-badge]//svg'))->toHaveCount(1);
})->with(['plain' => '', 'hex' => 'color="#ff0000"', 'label' => 'label="Hi"']);

it('prints a string badge on a navlist item, in its colour', function () {
    $html = renderBlade('<atom:navlist.item href="/x" badge="New" badge-color="red">Inbox</atom:navlist.item>');

    $badge = domQuery($html, '//a//span');
    expect($badge)->toHaveCount(1)
        ->and(trim($badge[0]->textContent))->toBe('New')
        ->and(domClasses($badge[0]))->toContain('bg-danger-bg');
});

it('still takes a badge slot on a navlist item', function () {
    $html = renderBlade('<atom:navlist.item href="/x">Inbox<x-slot:badge color="amber">NEW</x-slot:badge></atom:navlist.item>');

    $badge = domQuery($html, '//a//span');
    expect($badge)->toHaveCount(1)
        ->and(domClasses($badge[0]))->toContain('bg-warning-bg');
});
