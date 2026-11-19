<?php

use Illuminate\Support\Facades\DB;
use Jiannius\Atom\Tests\Fixtures\Item;

/** A builder on the `items` table of a second connection, from a model that names it. */
function colsOtherQuery()
{
    return (new class extends Item {
        protected $table = 'items';

        protected $connection = 'cols_other';
    })->newQuery();
}

/** `show columns` is MySQL-only, so sqlite connections are told apart by their cache seed and a MySQL one by `pretend()`. */
beforeEach(function () {
    config()->set('cache.default', 'array');

    config()->set('database.connections.cols_other', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'p_',
    ]);
    config()->set('database.connections.cols_plain', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);
    config()->set('database.connections.cols_mysql', [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dry', 'username' => 'dry', 'password' => '', 'prefix' => 'p_',
    ]);
});

it('runs show columns on the builder connection, naming the prefixed table, not on the default one', function () {
    $default = [];
    DB::connection('testing')->listen(function ($query) use (&$default) {
        if ($query->connectionName === 'testing') {
            $default[] = $query->sql;
        }
    });

    $queries = DB::connection('cols_mysql')->pretend(function () {
        Item::on('cols_mysql')->tableColumns();
    });

    expect(array_column($queries, 'query'))->toBe(['show columns from `p_items`'])
        ->and($default)->toBe([]);
});

it('sends no show columns to a connection that is not MySQL', function () {
    $sent = [];
    DB::connection('cols_plain')->listen(function ($query) use (&$sent) {
        $sent[] = $query->sql;
    });

    $columns = Item::on('cols_plain')->tableColumns();

    expect($columns->all())->toBe([])
        ->and(Item::on('cols_plain')->tableColumnType('id'))->toBeNull()
        ->and($sent)->toBe([]);
});

it('keeps a same-named table on two connections apart, in the cache', function () {
    cache()->put('table_cols_plain_:memory:_items_columns', [['Field' => 'id', 'Type' => 'integer']], now()->addDay());
    cache()->put('table_cols_other_:memory:_p_items_columns', [['Field' => 'id', 'Type' => 'varchar(36)'], ['Field' => 'due', 'Type' => 'date']], now()->addDay());

    $plain = Item::on('cols_plain')->tableColumns();
    $other = colsOtherQuery()->tableColumns();

    expect($plain->pluck('name')->all())->toBe(['id'])
        ->and($other->pluck('name')->all())->toBe(['id', 'due'])
        ->and(colsOtherQuery()->tableColumnType('id'))->toBe('varchar(36)')
        ->and(Item::on('cols_plain')->tableColumnType('id'))->toBe('integer')
        ->and(Item::on('cols_plain')->tableColumnType('due'))->toBeNull();
});

it('keeps one connection name apart when the database behind it changes', function () {
    cache()->put('table_cols_plain_tenant_a_items_columns', [['Field' => 'id', 'Type' => 'integer']], now()->addDay());
    cache()->put('table_cols_plain_tenant_b_items_columns', [['Field' => 'id', 'Type' => 'varchar(36)']], now()->addDay());

    config()->set('database.connections.cols_plain.database', 'tenant_a');
    DB::purge('cols_plain');
    $first = Item::on('cols_plain')->tableColumnType('id');

    config()->set('database.connections.cols_plain.database', 'tenant_b');
    DB::purge('cols_plain');
    $second = Item::on('cols_plain')->tableColumnType('id');

    expect($first)->toBe('integer')->and($second)->toBe('varchar(36)');
});
