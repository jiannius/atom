<?php

use Jiannius\Atom\Traits\AtomComponent;

/**
 * wirekey() builds a wire:key from the values a block depends on, so a morph
 * REPLACES the block when one of them changes. It used to array_filter() its
 * arguments, which dropped every falsy one — so a key built from a column index
 * could not tell index 0 from "none picked", and the block was morphed in place
 * instead of replaced on exactly that change.
 */
function wirekeyHost(): object
{
    return new class {
        use AtomComponent;
    };
}

it('changes the key when an argument changes to 0', function () {
    $host = wirekeyHost();

    expect($host->wirekey('date', 0))->not->toBe($host->wirekey('date', null))
        ->and($host->wirekey(0, 'DMY'))->not->toBe($host->wirekey(null, 'DMY'))
        ->and($host->wirekey('date', 0))->not->toBe($host->wirekey('date'));
});

it('keeps every falsy argument apart', function () {
    $host = wirekeyHost();

    $keys = collect([0, '0', '', false, null, []])
        ->map(fn ($value) => $host->wirekey('row', $value));

    expect($keys->unique()->count())->toBe(6);
});

it('gives the same key for the same arguments', function () {
    $host = wirekeyHost();

    expect($host->wirekey('row', 0, 'DMY'))->toBe($host->wirekey('row', 0, 'DMY'));
});

it('gives a fresh key per call when called with no arguments', function () {
    $host = wirekeyHost();

    expect($host->wirekey())->not->toBe($host->wirekey());
});
