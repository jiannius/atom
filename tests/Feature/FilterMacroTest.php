<?php

use Illuminate\Database\Eloquent\Builder;
use Jiannius\Atom\Tests\Fixtures\Item;

enum FilterTestKind: string
{
    case A = 'a';
    case B = 'b';
}

/** The same table with a hidden-attribute list and an enum cast. */
class FilterTestItem extends Item
{
    protected $table = 'items';

    protected $hidden = ['secret_token', 'api_key'];

    protected $casts = ['kind' => FilterTestKind::class];

    public static mixed $seen = null;

    /** @param Builder $query */
    public function scopeSeenAt(Builder $query, mixed $value): void
    {
        static::$seen = $value;
    }
}

/** $visible set: only these columns may be filtered. Mixed case on purpose. */
class FilterTestVisibleItem extends Item
{
    protected $table = 'items';

    protected $visible = ['id', 'name', 'Amount'];
}

/** A $hidden entry written in mixed case. */
class FilterTestMixedHiddenItem extends Item
{
    protected $table = 'items';

    protected $hidden = ['Secret_Token'];
}

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
        ['Field' => 'kind', 'Type' => 'varchar(20)'],
        ['Field' => 'secret_token', 'Type' => 'varchar(64)'],
        ['Field' => 'api_key', 'Type' => 'varchar(64)'],
        ['Field' => 'Secret_Token', 'Type' => 'varchar(64)'],
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
 * Key and value hardening. `$filters` is usually a plain public property, which a client
 * can replace wholesale through Livewire, so keys and values are as untrusted as input.
 */

/** A query on a MySQL grammar: compiling needs no server, and it quotes with backticks. */
function mysqlItems(string $model = FilterTestItem::class)
{
    seedItemColumns();

    config()->set('database.connections.mysql_dry', [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dry', 'username' => 'dry', 'password' => '',
    ]);

    return $model::on('mysql_dry');
}

/** The baseline query with one more constraint (the soft-delete scope is appended last). */
function withWhere(string $where): string
{
    return 'select * from `items` where '.$where.' and `items`.`deleted_at` is null';
}

function baselineSql(): string
{
    return mysqlItems()->toSql();
}

dataset('hostile filter keys', [
    'close paren' => ['claim_date.x)'],
    'double paren' => ['claim_date.x))'],
    'paren on column' => ['claim_date)'],
    'single quote' => ["claim_date'"],
    'double quote' => ['claim_date"'],
    'backtick' => ['claim_date`'],
    'hash comment' => ['claim_date#'],
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
    'middle operator ignored' => ['claim_date:regexp:='],
    'colon then paren' => ['claim_date.x:)'],
    'plain column sub-path' => ['name.x'],
    'integer column sub-path' => ['amount.x)'],
    'unknown column' => ['no_such_column'],
    'unknown dotted' => ['no_such.column'],
    'empty column' => [':>='],
    'semicolon' => ['claim_date;'],
    'hyphenated' => ['claim-date'],
    'digit first' => ['1claim_date'],
    'json bracket index' => ['meta.tags[0]'],
    'json hyphen segment' => ['meta.first-name'],
    'json trailing newline' => ["meta.lang\n"],
    'json quoted segment' => ['meta."x"'],
    'json arrow' => ['meta->x'],
]);

it('refuses a filter key that is not a plain column reference', function (string $key) {
    $query = mysqlItems()->filter([$key => '2025-01-01']);

    expect($query->toSql())->toBe(baselineSql())
        ->and($query->getBindings())->toBe([]);
})->with('hostile filter keys');

it('refuses a hostile key when filter() is called with a key and a value', function (string $key) {
    expect(mysqlItems()->filter($key, '2025-01-01 to 2025-02-01')->toSql())->toBe(baselineSql());
})->with('hostile filter keys');

it('quotes the date column with the grammar of the connection', function () {
    $sql = mysqlItems()->filter(['claim_date:>=' => '2025-01-01'])->toSql();

    expect($sql)->toBe(withWhere('date(`items`.`claim_date`) >= ?'));

    $range = mysqlItems()->filter(['claim_date' => '2025-01-01 to 2025-02-01'])->toSql();

    expect($range)->toBe(withWhere('date(`items`.`claim_date`) >= ? and date(`items`.`claim_date`) <= ?'));
});

it('quotes plain, datetime, JSON and enum columns with the grammar of the connection', function () {
    expect(mysqlItems()->filter(['amount:>=' => 5])->toSql())->toBe(withWhere('`items`.`amount` >= ?'))
        ->and(mysqlItems()->filter(['name:like' => 'a'])->toSql())->toBe(withWhere('`items`.`name` like ?'))
        ->and(mysqlItems()->filter(['starts_at:<' => '2025-01-01'])->toSql())->toBe(withWhere('`items`.`starts_at` < ?'))
        ->and(mysqlItems()->filter(['meta.lang' => 'en'])->toSql())->toBe(withWhere('json_unquote(json_extract(`items`.`meta`, \'$."lang"\')) = ?'))
        ->and(mysqlItems()->filter(['kind' => 'a,b'])->toSql())->toBe(withWhere('`kind` in (?, ?)'));
});

it('never lets a bad operator through on any branch', function (string $operator, string $key, mixed $value) {
    $query = mysqlItems()->filter(["$key:$operator" => $value]);

    expect($query->toSql())->toBe(baselineSql())
        ->and($query->getBindings())->toBe([]);
})->with(function () {
    $operators = ['regexp', 'rlike', '&', '|', '^', '<<', '>>', 'is', 'is not', '<=>', 'ilike', '~', 'like binary', 'sounds like', 'in', ') or 1', '=)'];
    $cases = ['amount' => 5, 'name' => 'a', 'meta.lang' => 'en', 'kind' => 'a', 'claim_date' => '2025-01-01', 'starts_at' => '2025-01-01'];

    foreach ($operators as $operator) {
        foreach ($cases as $key => $value) {
            yield "$operator on $key" => [$operator, $key, $value];
        }
    }
});

it('accepts every allowed operator in any case', function (string $operator) {
    $expected = strtolower($operator);
    $query = mysqlItems()->filter(["amount:$operator" => 5]);

    expect($query->toSql())->toBe(withWhere("`items`.`amount` $expected ?"));
})->with(['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'like', 'not like']);

it('ignores a filter key that is an int or not a string', function () {
    expect(mysqlItems()->filter([0 => 'x', 5 => ['a'], 7 => '2025-01-01'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(0, 'x')->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['a'], 'x')->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(null, 'x')->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(new stdClass, 'x')->toSql())->toBe(baselineSql());
});

it('refuses a hidden column on every branch', function (string $key) {
    $query = mysqlItems()->filter([$key => 'a']);

    expect($query->toSql())->toBe(baselineSql())
        ->and($query->getBindings())->toBe([]);
})->with(['secret_token', 'secret_token:like', 'secret_token:>=', 'SECRET_TOKEN:like', 'api_key', 'api_key:not like', 'api_key.x']);

it('refuses a hidden column given an array value, and still filters a visible one', function () {
    expect(mysqlItems()->filter(['secret_token' => ['a', 'b']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name:like' => 'a'])->toSql())->not->toBe(baselineSql());
});

it('does not treat an array value as a pattern or as a flat list when it is neither', function () {
    expect(mysqlItems()->filter(['name:like' => ['a', 'b']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name:not like' => ['a']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['id:like' => ['a', 'b']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name' => [['a']]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['kind' => [['a']]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['kind' => ['a', ['b']]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['claim_date' => ['2025-01-01']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['search' => ['a']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name' => new stdClass])->toSql())->toBe(baselineSql());
});

it('honours $visible: a column outside it is refused, in any case', function () {
    $model = FilterTestVisibleItem::class;

    expect(mysqlItems($model)->filter(['secret_token:like' => 'a'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems($model)->filter(['secret_token' => 'a'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems($model)->filter(['meta.lang' => 'en'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems($model)->filter(['kind' => 'a'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems($model)->filter(['name:like' => 'a'])->toSql())->toBe(withWhere('`items`.`name` like ?'))
        ->and(mysqlItems($model)->filter(['id' => 5])->toSql())->toBe(withWhere('`items`.`id` = ?'))
        ->and(mysqlItems($model)->filter(['amount:>=' => 5])->toSql())->toBe(withWhere('`items`.`amount` >= ?'));
});

it('refuses a hidden column whose real name is mixed case, whatever the key\'s case', function () {
    // the column is `Secret_Token` and so is the $hidden entry: the exact key matches the
    // column, so only folding the key's case before the comparison catches it
    expect(mysqlItems(FilterTestMixedHiddenItem::class)->filter(['Secret_Token:like' => 'a'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems(FilterTestMixedHiddenItem::class)->filter(['Secret_Token' => 'a'])->toSql())->toBe(baselineSql());
});

it('folds the case of a $hidden entry', function () {
    expect(mysqlItems(FilterTestMixedHiddenItem::class)->filter(['secret_token:like' => 'a'])->toSql())->toBe(baselineSql())
        ->and(mysqlItems(FilterTestMixedHiddenItem::class)->filter(['name:like' => 'a'])->toSql())->toBe(withWhere('`items`.`name` like ?'));
});

it('passes a date object through the way Laravel binds it', function (callable $make) {
    $date = $make();

    // a date column: whereDate() formats the object to Y-m-d
    $query = mysqlItems()->filter(['claim_date' => $date]);
    $expected = mysqlItems()->whereDate('items.claim_date', '=', $date);
    expect($query->toSql())->toBe($expected->toSql())->and($query->getBindings())->toBe($expected->getBindings());

    $query = mysqlItems()->filter(['claim_date:>=' => $date]);
    $expected = mysqlItems()->whereDate('items.claim_date', '>=', $date);
    expect($query->toSql())->toBe($expected->toSql())->and($query->getBindings())->toBe($expected->getBindings());

    // a datetime column and a plain column: the object itself is the binding
    foreach (['starts_at' => 'starts_at', 'amount:>=' => 'amount', 'meta.at' => 'meta->at'] as $key => $column) {
        $operator = str_contains($key, ':') ? '>=' : '=';
        $query = mysqlItems()->filter([$key => $date]);
        $expected = mysqlItems()->where("items.$column", $operator, $date);

        expect($query->toSql())->toBe($expected->toSql())
            ->and($query->getBindings())->toHaveCount(1)
            ->and($query->getBindings()[0])->toBe($date);
    }

    // a scope gets the value as sent
    FilterTestItem::$seen = null;
    mysqlItems()->filter(['seenAt' => $date])->toSql();
    expect(FilterTestItem::$seen)->toBe($date);

    // a pattern is text, never a date object
    expect(mysqlItems()->filter(['name:like' => $date])->toSql())->toBe(baselineSql());
})->with([
    'carbon' => [fn () => now()],
    'immutable' => [fn () => new DateTimeImmutable('2025-01-01 10:00:00')],
    'mutable' => [fn () => new DateTime('2025-01-01 10:00:00')],
]);

it('treats a list on a plain or JSON column by operator', function () {
    expect(mysqlItems()->filter(['name' => ['a', 'b']])->toSql())->toBe(withWhere('`items`.`name` in (?, ?)'))
        ->and(mysqlItems()->filter(['name:=' => ['a', 'b']])->toSql())->toBe(withWhere('`items`.`name` in (?, ?)'))
        ->and(mysqlItems()->filter(['name:!=' => ['a', 'b']])->toSql())->toBe(withWhere('`items`.`name` not in (?, ?)'))
        ->and(mysqlItems()->filter(['name:<>' => ['a', 'b']])->toSql())->toBe(withWhere('`items`.`name` not in (?, ?)'))
        ->and(mysqlItems()->filter(['amount:>=' => [1, 2]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['amount:<' => [1, 2]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['amount:>' => [1, 2]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['amount:<=' => [1, 2]])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name:like' => ['a']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['name:not like' => ['a']])->toSql())->toBe(baselineSql())
        ->and(mysqlItems()->filter(['meta.tags' => ['a']])->toSql())->toBe(withWhere('json_contains(`items`.`meta`, ?, \'$."tags"\')'))
        ->and(mysqlItems()->filter(['meta.tags:!=' => ['a']])->toSql())->toBe(withWhere('not json_contains(`items`.`meta`, ?, \'$."tags"\')'))
        ->and(mysqlItems()->filter(['meta.tags:>=' => ['a']])->toSql())->toBe(baselineSql());
});

it('takes dates in a list on a plain column', function () {
    $a = new DateTimeImmutable('2025-01-01 10:00:00');
    $b = now();
    $query = mysqlItems()->filter(['amount' => [$a, $b]]);
    $expected = mysqlItems()->whereIn('items.amount', [$a, $b]);

    expect($query->toSql())->toBe(withWhere('`items`.`amount` in (?, ?)'))
        ->and($query->toSql())->toBe($expected->toSql())
        ->and($query->getBindings())->toBe([$a, $b]);

    $not = mysqlItems()->filter(['amount:!=' => [$a, $b]]);

    expect($not->toSql())->toBe(withWhere('`items`.`amount` not in (?, ?)'))
        ->and($not->getBindings())->toBe([$a, $b]);
});

it('takes a flat list of scalars on a plain column and an enum column', function () {
    expect(mysqlItems()->filter(['name' => ['a', 'b']])->toSql())->toBe(withWhere('`items`.`name` in (?, ?)'))
        ->and(mysqlItems()->filter(['kind' => [FilterTestKind::A, 'b']])->getBindings())->toBe(['a', 'b'])
        ->and(mysqlItems()->filter(['kind' => FilterTestKind::A])->getBindings())->toBe(['a'])
        ->and(mysqlItems()->filter(['kind:!=' => 'a'])->toSql())->toBe(withWhere('`kind` not in (?)'));
});

it('never throws or builds a mismatched query whatever shape the value has', function () {
    $values = [
        'string' => 'x', 'padded' => ' a, b ', 'zero string' => '0', 'zero' => 0, 'int' => 5, 'float' => 1.5,
        'true' => true, 'false' => false, 'null' => null, 'empty string' => '', 'empty array' => [],
        'list' => ['a', 'b'], 'mixed list' => ['a', 1, 1.5, true], 'list with null' => ['a', null],
        'nested' => [['a']], 'empty nested' => [[]], 'keyed nested' => ['a' => ['b']], 'keyed' => ['a' => 'b'],
        'object in list' => [new stdClass], 'object' => new stdClass, 'carbon' => now(), 'immutable' => new DateTimeImmutable('2025-01-01'), 'dates in list' => [now(), new DateTimeImmutable('2025-01-01')], 'stringable' => str('x'), 'enum' => FilterTestKind::B,
        'range' => '2025-01-01 to 2025-02-01', 'open range' => '2025-01-01 to', 'bare to' => 'to',
        'like pattern' => '%a*', 'long' => str_repeat('a', 5000),
    ];
    $keys = ['claim_date', 'starts_at', 'amount', 'name', 'meta', 'meta.lang', 'kind', 'no_such_column'];
    // named scopes are left out: a scope is host code and gets the value as sent
    $operators = [null, '=', '!=', '>=', 'like', 'not like'];
    $checked = 0;

    foreach ($keys as $key) {
        foreach ($operators as $operator) {
            foreach ($values as $label => $value) {
                $query = mysqlItems()->filter([$operator ? "$key:$operator" : $key => $value]);

                // a placeholder per binding: a nested list must not skew the two apart
                expect(substr_count($query->toSql(), '?'))->toBe(count($query->getBindings()), "$key:$operator with $label");
                $checked++;
            }
        }
    }

    expect($checked)->toBe(count($keys) * count($operators) * count($values));
});

it('still dispatches named scopes by key', function () {
    $query = mysqlItems()->filter(['status' => ['a', 'b']]);

    expect($query->toSql())->toContain('`status` in (?, ?)')
        ->and($query->getBindings())->toBe(['a', 'b']);
});
