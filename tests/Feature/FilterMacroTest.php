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

it('applies a filter whose value is a falsy scalar on a raw column', function () {
    // Use the array cache store — the seed below (and the macro's own
    // cache()->remember) must share a store, and testbench's default resolves
    // to `database`, whose `cache` table isn't migrated here → a write error.
    config()->set('cache.default', 'array');

    // The raw-column branch needs tableColumnType(), which runs the MySQL-only
    // `show columns`. Seed the cache it reads so the branch is reachable on sqlite.
    cache()->put('table_items_columns', [
        ['Field' => 'id', 'Type' => 'integer'],
        ['Field' => 'name', 'Type' => 'varchar(255)'],
        ['Field' => 'amount', 'Type' => 'integer'],
    ], now()->addDays(7));

    Item::factory()->create(['amount' => 0]);
    Item::factory()->create(['amount' => 5]);

    // `amount` is a plain (raw) column — no scope, no enum cast. blank('0') is
    // false, so '0' is a real constraint. The applier used to re-test `&& $value`
    // and drop it because '0' is PHP-falsy, contradicting the blank() guard and
    // silently returning unfiltered rows. The where() must be applied.
    $results = Item::query()->filter(['amount' => '0'])->get();

    expect($results)->toHaveCount(1)
        ->and((int) $results->first()->amount)->toBe(0);
});
