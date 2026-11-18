<?php

use Illuminate\Support\Facades\DB;
use Jiannius\Atom\Tests\Fixtures\Item;

/** The same table name on a model that lives on a second connection. */
class ColumnsOtherItem extends Item
{
    protected $table = 'items';

    protected $connection = 'cols_other';
}

/** Two connections, one with a table prefix; `show columns` is MySQL-only, so each is told apart by its cache seed. */
beforeEach(function () {
    config()->set('cache.default', 'array');

    config()->set('database.connections.cols_other', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'p_',
    ]);
    // its own connection: the request memo outlives a test, and pretend() memoises an empty answer
    config()->set('database.connections.cols_pretend', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'p_',
    ]);
    config()->set('database.connections.cols_plain', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);
});

it('runs show columns on the builder connection, naming the prefixed table, not on the default one', function () {
    $default = [];
    DB::connection('testing')->listen(function ($query) use (&$default) {
        if ($query->connectionName === 'testing') {
            $default[] = $query->sql;
        }
    });

    $queries = DB::connection('cols_pretend')->pretend(function () {
        Item::on('cols_pretend')->tableColumns();
    });

    expect(array_column($queries, 'query'))->toBe(['show columns from `p_items`'])
        ->and($default)->toBe([]);
});

it('keeps a same-named table on two connections apart, in the cache', function () {
    cache()->put('table_cols_plain_items_columns', [['Field' => 'id', 'Type' => 'integer']], now()->addDay());
    cache()->put('table_cols_other_p_items_columns', [['Field' => 'id', 'Type' => 'varchar(36)'], ['Field' => 'due', 'Type' => 'date']], now()->addDay());

    $plain = Item::on('cols_plain')->tableColumns();
    $other = ColumnsOtherItem::query()->tableColumns();

    expect($plain->pluck('name')->all())->toBe(['id'])
        ->and($other->pluck('name')->all())->toBe(['id', 'due'])
        ->and(ColumnsOtherItem::query()->tableColumnType('id'))->toBe('varchar(36)')
        ->and(Item::on('cols_plain')->tableColumnType('id'))->toBe('integer')
        ->and(Item::on('cols_plain')->tableColumnType('due'))->toBeNull();
});
