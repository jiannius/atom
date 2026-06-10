<?php

use Jiannius\Atom\Tests\Fixtures\Item;

// NOTE: filter()'s raw-column branch uses `show columns from` (MySQL-only).
// On sqlite we exercise the named-scope path + the blank-value short-circuit,
// both of which are connection-agnostic.

it('applies the search named scope via filter()', function () {
    Item::factory()->create(['name' => 'apple']);
    Item::factory()->create(['name' => 'banana']);

    $results = Item::query()->filter(['search' => 'app'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('apple');
});

it('skips blank filter values without hitting column introspection', function () {
    Item::factory()->count(3)->create();

    // `name` is a real (raw) column with no scope. Before the blank() guard,
    // filter(['name' => null]) fell through to tableColumnType() → `show columns`
    // and crashed on sqlite. The guard makes a blank value a no-op (no constraint,
    // no introspection), so all rows are returned and nothing throws.
    expect(Item::query()->filter(['name' => null, 'status' => '', 'search' => null])->count())->toBe(3);
});
