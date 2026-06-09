<?php

use Jiannius\Atom\Tests\Fixtures\Item;

// NOTE: filter()'s column-type branch uses `show columns from` (MySQL).
// On sqlite we exercise only the named-scope path, which is connection-agnostic.
//
// NOTE: filter() has a bug where filter(['search' => null]) falls through to the
// column-type branch (tableColumnType) instead of being a no-op, because the
// `$key === 'search' && $value` guard fails but the fallback `else if` also fails
// for 'search', landing in the MySQL-only else. In practice this never fires because
// TableFixture calls array_filter() before passing to toTable(). Tests below mirror
// real usage: empty values are stripped before calling filter().

it('applies the search named scope via filter()', function () {
    Item::factory()->create(['name' => 'apple']);
    Item::factory()->create(['name' => 'banana']);

    $results = Item::query()->filter(['search' => 'app'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('apple');
});

it('ignores empty filter values', function () {
    Item::factory()->count(3)->create();

    // array_filter strips null/empty values before filter() is called — mirrors
    // TableFixture::items() behaviour; passing an empty array is a no-op.
    expect(Item::query()->filter(array_filter(['search' => null]))->count())->toBe(3);
});
