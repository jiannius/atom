<?php

use Jiannius\Atom\Tests\Fixtures\Item;

// NOTE: filter()'s raw-column branch uses `show columns from` (MySQL-only).
// On sqlite we exercise the named-scope path + the blank-value short-circuit,
// both of which are connection-agnostic.

/**
 * The raw-column branch needs tableColumnType(), which runs the MySQL-only
 * `show columns`. Seed the cache it reads so the branch is reachable on sqlite.
 * tableColumns() memoises per table for the whole process, so every test in
 * this file must seed the same set.
 */
function seedItemColumns(): void
{
    // Use the array cache store — the seed below (and the macro's own
    // cache()->remember) must share a store, and testbench's default resolves
    // to `database`, whose `cache` table isn't migrated here → a write error.
    config()->set('cache.default', 'array');

    cache()->put('table_items_columns', [
        ['Field' => 'id', 'Type' => 'integer'],
        ['Field' => 'name', 'Type' => 'varchar(255)'],
        ['Field' => 'amount', 'Type' => 'integer'],
        ['Field' => 'claim_date', 'Type' => 'date'],
        ['Field' => 'starts_at', 'Type' => 'datetime'],
        ['Field' => 'meta', 'Type' => 'json'],
    ], now()->addDays(7));
}

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

    seedItemColumns();

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


/*
 * Key hardening. `$filters` is usually a plain public property, which a client can
 * replace wholesale through Livewire, so the keys are as untrusted as the values.
 */

dataset('hostile filter keys', [
    'close paren' => ['claim_date.x) or 1=1 -- '],
    'double paren' => ['claim_date.x)) or (select 1) -- '],
    'subselect' => ['claim_date.x) and (select 1 from items) = 1 -- '],
    'single quote' => ["claim_date'"],
    'double quote' => ['claim_date"'],
    'backtick' => ['claim_date`'],
    'hash comment' => ['claim_date)#'],
    'block comment' => ['claim_date/**/'],
    'line comment' => ['claim_date-- '],
    'extra dots' => ['claim_date.a.b'],
    'numeric sub-path' => ['claim_date.0'],
    'trailing dot' => ['claim_date.'],
    'leading dot' => ['.claim_date'],
    'qualified table' => ['items.claim_date'],
    'newline' => ["claim_date\n"],
    'space' => ['claim_date .x'],
    'datetime sub-path' => ['starts_at.x)'],
    'datetime quote' => ['starts_at"'],
    'two colons' => ['claim_date:>=:x'],
    'colon then paren' => ['claim_date.x:) or 1=1 -- '],
    'plain column sub-path' => ['name.x'],
    'integer column sub-path' => ['amount.x)'],
    'unknown column' => ['no_such_column'],
    'unknown dotted' => ['no_such.column'],
    'empty column' => [':>='],
    'semicolon' => ['claim_date;drop table items'],
]);

it('refuses a filter key that is not a plain column reference', function (string $key) {
    seedItemColumns();

    $query = Item::query()->filter([$key => '2025-01-01']);

    expect($query->toSql())->toBe(Item::query()->toSql())
        ->and($query->getBindings())->toBe([]);
})->with('hostile filter keys');

it('refuses a hostile key when filter() is called with a key and a value', function (string $key) {
    seedItemColumns();

    $query = Item::query()->filter($key, '2025-01-01 to 2025-02-01');

    expect($query->toSql())->toBe(Item::query()->toSql());
})->with('hostile filter keys');

it('ignores an array value on a date column', function (string $key) {
    seedItemColumns();

    $query = Item::query()->filter([$key => ['2025-01-01) or 1=1 -- ', 'x']]);

    expect($query->toSql())->toBe(Item::query()->toSql());
})->with(['claim_date', 'claim_date:>=', 'claim_date.x) -- ', 'starts_at']);

it('never lets a hostile operator onto a date column unquoted', function () {
    seedItemColumns();

    $query = Item::query()->filter(['claim_date:) or 1=1 -- ' => '2025-01-01']);

    // the operator is not in the grammar's list, so it is demoted to a bound value
    expect($query->toSql())->toBe(Item::query()->whereDate('items.claim_date', '2025-01-01')->toSql())
        ->and($query->toSql())->not->toContain('1=1')
        ->and($query->getBindings())->toBe([') or 1=1 -- ']);
});

it('puts nothing from a crafted date key into the SQL text', function () {
    seedItemColumns();

    $keys = ["claim_date.x) or 1=1 -- ", "claim_date'", 'claim_date`', 'claim_date.a.b', 'claim_date:) or 1=1'];

    foreach ($keys as $key) {
        foreach (['2025-01-01', '2025-01-01 to 2025-02-01', 'to 2025-02-01', '2025-01-01 to'] as $value) {
            $sql = Item::query()->filter([$key => $value])->toSql();

            expect($sql)->not->toContain('1=1')
                ->and($sql)->not->toContain('`')
                ->and($sql)->not->toContain('--')
                ->and($sql)->not->toContain('claim_date.')
                ->and($sql)->not->toContain("claim_date'");
        }
    }
});

it('filters a date column by equality, operator and range through quoted whereDate', function () {
    seedItemColumns();

    $eq = Item::query()->filter(['claim_date' => '2025-01-01']);
    $expected = Item::query()->whereDate('items.claim_date', '=', '2025-01-01');
    expect($eq->toSql())->toBe($expected->toSql())->and($eq->getBindings())->toBe($expected->getBindings());

    $op = Item::query()->filter(['claim_date:>=' => '2025-01-01']);
    $expected = Item::query()->whereDate('items.claim_date', '>=', '2025-01-01');
    expect($op->toSql())->toBe($expected->toSql())->and($op->getBindings())->toBe($expected->getBindings());

    $range = Item::query()->filter(['claim_date' => '2025-01-01 to 2025-02-01']);
    $expected = Item::query()->whereDate('items.claim_date', '>=', '2025-01-01')->whereDate('items.claim_date', '<=', '2025-02-01');
    expect($range->toSql())->toBe($expected->toSql())->and($range->getBindings())->toBe($expected->getBindings());

    $from = Item::query()->filter(['claim_date' => '2025-01-01 to']);
    $expected = Item::query()->whereDate('items.claim_date', '>=', '2025-01-01');
    expect($from->toSql())->toBe($expected->toSql())->and($from->getBindings())->toBe($expected->getBindings());

    $until = Item::query()->filter(['claim_date' => 'to 2025-02-01']);
    $expected = Item::query()->whereDate('items.claim_date', '<=', '2025-02-01');
    expect($until->toSql())->toBe($expected->toSql())->and($until->getBindings())->toBe($expected->getBindings());
});

it('filters a datetime column with plain comparisons', function () {
    seedItemColumns();

    $range = Item::query()->filter(['starts_at' => '2025-01-01 to 2025-02-01']);
    $expected = Item::query()->where('items.starts_at', '>=', '2025-01-01')->where('items.starts_at', '<=', '2025-02-01');
    expect($range->toSql())->toBe($expected->toSql())->and($range->getBindings())->toBe($expected->getBindings());

    $op = Item::query()->filter(['starts_at:<' => '2025-01-01']);
    $expected = Item::query()->where('items.starts_at', '<', '2025-01-01');
    expect($op->toSql())->toBe($expected->toSql());
});

it('keeps the key:operator and JSON path shapes on other columns', function () {
    seedItemColumns();

    $op = Item::query()->filter(['amount:>=' => 5]);
    $expected = Item::query()->where('items.amount', '>=', 5);
    expect($op->toSql())->toBe($expected->toSql())->and($op->getBindings())->toBe($expected->getBindings());

    $like = Item::query()->filter(['name:like' => 'app']);
    $expected = Item::query()->where('items.name', 'like', '%app%');
    expect($like->toSql())->toBe($expected->toSql())->and($like->getBindings())->toBe($expected->getBindings());

    $json = Item::query()->filter(['meta.lang' => 'en']);
    $expected = Item::query()->where('items.meta->lang', 'en');
    expect($json->toSql())->toBe($expected->toSql())->and($json->getBindings())->toBe($expected->getBindings());

    $deep = Item::query()->filter(['meta.address.city' => 'KL']);
    $expected = Item::query()->where('items.meta->address->city', 'KL');
    expect($deep->toSql())->toBe($expected->toSql());

    $contains = Item::query()->filter(['meta.tags' => ['a', 'b']]);
    $expected = Item::query()->whereJsonContains('items.meta->tags', ['a', 'b']);
    expect($contains->toSql())->toBe($expected->toSql());
});

it('still dispatches named scopes by key', function () {
    seedItemColumns();

    $query = Item::query()->filter(['status' => ['a', 'b']]);

    expect($query->toSql())->toContain('"status" in (?, ?)')
        ->and($query->getBindings())->toBe(['a', 'b']);
});
