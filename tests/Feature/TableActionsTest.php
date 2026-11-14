<?php

use Illuminate\Support\Facades\Blade;

it('renders a trailing actions cell with a dropdown menu', function () {
    $html = Blade::render('<atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>');

    expect($html)->toContain('data-atom-table-actions')
        ->and($html)->toContain('click.stop')
        ->and($html)->toContain('Edit');
});

it('names the row ⋯ trigger "More actions", with the shared more icon, and opens its menu at the end', function () {
    $html = Blade::render('<atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>');
    $header = Blade::render('<atom:page.header title="T"><x-slot:menu><atom:menu.item>Delete</atom:menu.item></x-slot:menu></atom:page.header>');

    $trigger = domQuery($html, '//*[@data-atom-dropdown]//button')[0];
    $headerTrigger = domQuery($header, '//*[@data-atom-dropdown]//button')[0];

    expect($trigger->getAttribute('aria-label'))->toBe('More actions')
        ->and($trigger->getAttribute('aria-label'))->toBe($headerTrigger->getAttribute('aria-label'))
        ->and($html)->toContain("placement: 'bottom-end'")
        // the same three-dot glyph as the header's trigger (the `more` icon, not the lucide ellipsis)
        ->and(domQuery($html, '//*[@data-atom-dropdown]//button//circle'))->toHaveCount(3)
        ->and(domQuery($header, '//*[@data-atom-dropdown]//button//circle'))->toHaveCount(3);
});
