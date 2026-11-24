<?php

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Services\TableSort;
use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Jiannius\Atom\Tests\Fixtures\TableTrashedFixture;
use Livewire\Livewire;

/**
 * `$_table` is a public Livewire property, so a client can $wire.set() any of it. The signed `raw:` sort
 * gate covers the sort; these cover the rest: `max_rows` is the page size handed to paginate(), and
 * `show_trashed` lists soft-deleted rows the host may never have offered. Sorting on a hidden column
 * is the third: orderBy() on a password or token column is a row-order oracle for its value.
 */

// the `_table` shapes a client can send, and the gate refuses
dataset('bad max rows', [
    'zero' => [0],
    'negative' => [-5],
    'over the cap' => [501],
    'huge' => [10_000_000],
    'text' => ['abc'],
    'numeric string over the cap' => ['501'],
    'zero string' => ['0'],
    'padded string' => [' 50'],
    'four digits' => ['1000'],
    'float string' => ['50.5'],
    'float' => [50.5],
    'array' => [[50]],
    'bool' => [true],
]);

it('refuses a max_rows the pager cannot offer', function ($value) {
    Livewire::test(TableFixture::class)->set('_table.max_rows', $value)->assertForbidden();
})->with('bad max rows');

it('refuses a max_rows carried by a wholesale _table update', function ($value) {
    Livewire::test(TableFixture::class)
        ->set('_table', ['sort' => ['column' => null, 'direction' => null], 'max_rows' => $value])
        ->assertForbidden();
})->with('bad max rows');

it('lets a select send max_rows as a numeric string, and reads it as that number', function () {
    Item::factory()->count(3)->create();

    $test = Livewire::test(TableFixture::class)->set('_table.max_rows', '2')->assertOk();

    expect(withLivewireContext($test->instance(), fn ($c) => $c->items())->perPage())->toBe(2);

    Livewire::test(TableFixture::class)->set('_table', ['sort' => ['column' => null, 'direction' => null], 'max_rows' => '50'])->assertOk();
});

it('refuses a wholesale _table that is not an array', function ($value) {
    Livewire::test(TableFixture::class)->set('_table', $value)->assertForbidden();
})->with(['string' => ['x'], 'null' => [null], 'int' => [5], 'bool' => [false]]);

it('refuses a write below max_rows', function () {
    Livewire::test(TableFixture::class)->set('_table.max_rows.0', 50)->assertForbidden();
});

it('lets the pager menu sizes through', function (int $rows) {
    Livewire::test(TableFixture::class)->set('_table.max_rows', $rows)->assertOk();
})->with([1, 50, 100, 200, 400, TableSort::MAX_ROWS]);

it('clamps a max_rows PHP code left unusable instead of paginating by it', function ($stored, int $expected) {
    Item::factory()->count(3)->create();

    $test = Livewire::test(TableFixture::class);
    $test->instance()->_table['max_rows'] = $stored;

    $paginator = withLivewireContext($test->instance(), fn ($c) => $c->items());

    expect($paginator->perPage())->toBe($expected);
})->with([
    'text' => ['abc', 100],
    'array' => [[1, 2], 100],
    'null' => [null, 100],
    'zero' => [0, 100],
    'negative' => [-1, 100],
    'numeric string' => ['25', 25],
    'over the cap' => [100_000, TableSort::MAX_ROWS],
    'a sane value' => [25, 25],
]);

it('refuses a client that turns the trashed view on when the table has no toggle', function () {
    Livewire::test(TableFixture::class)->set('_table.show_trashed', true)->assertForbidden();
});

it('refuses the trashed view carried by a wholesale _table update when the table has no toggle', function () {
    Livewire::test(TableFixture::class)
        ->set('_table', ['sort' => ['column' => null, 'direction' => null], 'show_trashed' => true])
        ->assertForbidden();
});

it('refuses a $toggle of the trashed view when the table has no toggle', function () {
    Livewire::test(TableFixture::class)->toggle('_table.show_trashed')->assertForbidden();
});

it('lets the client turn the trashed view off, with or without the toggle', function () {
    Livewire::test(TableFixture::class)->set('_table.show_trashed', false)->assertOk();
    Livewire::test(TableTrashedFixture::class)->set('_table.show_trashed', false)->assertOk();
});

it('lets the client toggle the trashed view when the table rendered its toggle', function () {
    $live = Item::factory()->create();
    $trashed = Item::factory()->create();
    $trashed->delete();

    $test = Livewire::test(TableTrashedFixture::class)->toggle('_table.show_trashed');

    $ids = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('id')->all());

    expect($ids)->toBe([$trashed->id])->and($ids)->not->toContain($live->id);
});

it('refuses a trashed value that is not a bool, and a write below it', function () {
    Livewire::test(TableTrashedFixture::class)->set('_table.show_trashed', 'yes')->assertForbidden();
    Livewire::test(TableTrashedFixture::class)->set('_table.show_trashed', [1])->assertForbidden();
    Livewire::test(TableTrashedFixture::class)->set('_table.show_trashed.0', true)->assertForbidden();
});

it('does not let the browser say the table has a toggle', function () {
    expect(fn () => Livewire::test(TableFixture::class)->set('_tableTrashed', true))
        ->toThrow('Cannot update locked property');
});

it('keeps the toggle flag across requests once the toggle has rendered', function () {
    $test = Livewire::test(TableTrashedFixture::class);

    expect($test->get('_tableTrashed'))->toBeTrue();

    // a later request hydrates a fresh instance from the snapshot: the flag has to be in it
    $test->set('_table.show_trashed', true)->set('_table.show_trashed', false)->assertOk();
});

it('does not 500 on a model without soft deletes when the trashed view is on', function () {
    $model = new class extends Model {
        protected $table = 'items';
    };

    Item::factory()->count(2)->create();

    $test = Livewire::test(TableFixture::class);
    $test->instance()->_table['show_trashed'] = true;

    $rows = withLivewireContext($test->instance(), fn () => $model::query()->toTable());

    expect($rows->total())->toBe(2);
});

it('refuses a client sort on a hidden column', function () {
    $model = new class extends Model {
        protected $table = 'items';

        protected $hidden = ['Name'];
    };

    $first = Item::factory()->create(['name' => 'a']);
    $second = Item::factory()->create(['name' => 'b']);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'name')
        ->set('_table.sort.direction', 'asc');

    $ids = withLivewireContext($test->instance(), fn () => $model::query()->toTable()->pluck('id')->all());

    // the sort is dropped, so the default newest-first order shows, not name ascending
    expect($ids)->toBe([$second->id, $first->id]);

    // and the same column on a model that does not hide it does sort
    $visible = withLivewireContext($test->instance(), fn () => Item::query()->toTable()->pluck('id')->all());

    expect($visible)->toBe([$first->id, $second->id]);
});

it('sorts a visible column of a model that hides others', function () {
    $model = new class extends Model {
        protected $table = 'items';

        protected $hidden = ['status'];
    };

    Item::factory()->create(['amount' => 5]);
    Item::factory()->create(['amount' => 1]);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'amount')
        ->set('_table.sort.direction', 'asc');

    $amounts = withLivewireContext($test->instance(), fn () => $model::query()->toTable()->pluck('amount')->all());

    expect($amounts)->toBe([1, 5]);
});

it('decides which columns a client may sort on', function (string $column, array $hidden, array $visible, bool $expected) {
    $model = (new class extends Model {
        protected $table = 'items';
    })->setHidden($hidden)->setVisible($visible);

    expect(TableSort::isSortable($model, $column))->toBe($expected);
})->with([
    'plain' => ['amount', [], [], true],
    'hidden' => ['password', ['password'], [], false],
    'hidden, other case' => ['PassWord', ['password'], [], false],
    'hidden json path' => ['password->x', ['password'], [], false],
    'hidden dotted' => ['password.x', ['password'], [], false],
    'hidden behind a table name' => ['users.password', ['password'], [], false],
    'visible listed' => ['amount', [], ['amount'], true],
    'visible, not listed' => ['created_at', [], ['amount'], false],
    'visible json path' => ['amount->x', [], ['amount'], true],
    'visible, behind the model table' => ['items.amount', [], ['amount'], true],
    'visible, behind the model table in another case' => ['ITEMS.amount', [], ['amount'], true],
    'visible, behind another table' => ['orders.amount', [], ['amount'], false],
    'hidden, behind the model table' => ['items.password', ['password'], [], false],
    'the table name alone is not a column of it' => ['items', [], ['amount'], false],
    // the identifier shape: a denylist on a raw string let these through to orderBy()
    'leading space' => [' password', ['password'], [], false],
    'trailing space' => ['password ', ['password'], [], false],
    'trailing newline' => ["password\n", ['password'], [], false],
    'backticked' => ['`password`', ['password'], [], false],
    'quoted' => ['"password"', ['password'], [], false],
    'aliased' => ['password as x', ['password'], [], false],
    'aliased, other column' => ['amount as x', [], [], false],
    'a function' => ['lower(amount)', [], [], false],
    'a subquery' => ['(select 1)', [], [], false],
    'a comma' => ['amount,password', [], [], false],
    'empty' => ['', [], [], false],
    'a leading digit' => ['1amount', [], [], false],
    'a hyphen' => ['my-column', [], [], false],
    'a semicolon' => ['amount;', [], [], false],
    'a plain column with an underscore' => ['created_at', [], [], true],
    'a dotted column' => ['items.created_at', [], [], true],
    'json path' => ['data->a->b', [], [], true],
    'json path with a quote' => ['data->\'a', [], [], false],
    'json path hidden at the root' => ['password->a', ['password'], [], false],
    'json path hidden below' => ['data->password', ['password'], [], false],
]);

it('drops a client sort on a column that is not a plain name, and the default order shows', function (string $column) {
    $first = Item::factory()->create(['name' => 'a']);
    $second = Item::factory()->create(['name' => 'b']);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', $column)
        ->set('_table.sort.direction', 'asc');

    $ids = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('id')->all());

    expect($ids)->toBe([$second->id, $first->id]);
})->with([' name', 'name ', "name\n", '`name`', 'name as x', 'lower(name)', 'name,id']);

it('sorts a column qualified with the model table', function () {
    $model = new class extends Model {
        protected $table = 'items';

        protected $visible = ['amount'];
    };

    Item::factory()->create(['amount' => 5]);
    Item::factory()->create(['amount' => 1]);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'items.amount')
        ->set('_table.sort.direction', 'asc');

    expect(withLivewireContext($test->instance(), fn () => $model::query()->toTable()->pluck('amount')->all()))->toBe([1, 5]);
});

it('says once per process and column that a client sort column was dropped', function () {
    $model = new class extends Model {
        protected $table = 'items';

        protected $hidden = ['created_at'];
    };

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'created_at')
        ->set('_table.sort.direction', 'asc');

    // a class name that no earlier test in this process has used, so its first drop is the one logged
    $lines = [];
    \Illuminate\Support\Facades\Log::listen(function ($message) use (&$lines) {
        $lines[] = [$message->level, $message->context];
    });

    withLivewireContext($test->instance(), function () use ($model) {
        $model::query()->toTable();
        $model::query()->toTable();
    });

    expect($lines)->toHaveCount(1)
        ->and($lines[0][0])->toBe('debug')
        ->and($lines[0][1]['column'])->toBe('created_at')
        ->and($lines[0][1]['model'])->toBe(get_class($model));
});

it('does not log a drop for a signed raw sort or a sortable column', function () {
    $lines = [];
    \Illuminate\Support\Facades\Log::listen(function ($message) use (&$lines) {
        $lines[] = $message;
    });

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'amount')
        ->set('_table.sort.direction', 'asc');

    withLivewireContext($test->instance(), fn ($c) => $c->items());

    expect($lines)->toBe([]);
});
