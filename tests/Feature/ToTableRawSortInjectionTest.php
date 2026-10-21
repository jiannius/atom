<?php

use Jiannius\Atom\Services\TableSort;
use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\OwnTableFixture;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Issue #54 — toTable()'s "raw:" sort had no allow-list.
 *
 * _table is a plain public Livewire property (Traits\WithTable), so a
 * client could $wire.set('_table.sort.column', 'raw:<anything>') and
 * toTable() forwarded the substring after "raw:" straight into
 * orderByRaw(), regardless of the sort= attributes declared on
 * <atom:table.column> — boolean-blind SQL injection via row order.
 *
 * Fixed with a signed token: components/table/column.blade.php signs a
 * declared raw: sort (Services\TableSort::sign()) before it ever reaches the
 * browser, and the raw: sort gate (AtomServiceProvider's update listener) refuses (403) any
 * client update that would leave _table.sort.column holding an unsigned or
 * tampered raw: value — before it ever reaches toTable(). An unsigned raw:
 * value can therefore only have come from PHP, where it stays trusted.
 */

it('refuses a client-supplied raw: sort with no signature at all', function () {
    Item::factory()->count(2)->create(); // so paginate would run the data query, not just count()

    // A value no <atom:table.column sort="..."> ever emits.
    $injected = "(CASE WHEN (SELECT 1) = 1 THEN 0 ELSE 1 END)";

    Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'raw:'.$injected)
        ->assertForbidden();
});

it('closes the boolean-blind exfiltration path the same way', function () {
    // Rows whose visible order a guest could observe if either probe ran.
    Item::factory()->create(['name' => 'a']);
    Item::factory()->create(['name' => 'b']);

    // A table the component never exposes.
    DB::statement('CREATE TABLE secrets (id integer primary key, token text)');
    DB::table('secrets')->insert(['token' => 'S3CR3T']);

    // Per-row oracle: when the secret's first char matches, sort key = name;
    // otherwise the key is swapped. Both guesses must be refused identically
    // — neither succeeding nor failing differently — or the bit still leaks
    // through which guess "worked".
    $probe = fn ($char) => 'raw:(CASE WHEN (SELECT substr(token,1,1) FROM secrets LIMIT 1) = \''.$char.'\' '
        .'THEN name ELSE (CASE WHEN name=\'a\' THEN \'z\' ELSE \'a\' END) END)';

    $matching = Livewire::test(TableFixture::class)->set('_table.sort.column', $probe('S'));
    $mismatching = Livewire::test(TableFixture::class)->set('_table.sort.column', $probe('Z'));

    $matching->assertForbidden();
    $mismatching->assertForbidden();
});

it('still sorts a Blade-declared raw: expression once signed', function () {
    Item::factory()->create(['amount' => 5, 'name' => 'b']);
    Item::factory()->create(['amount' => 1, 'name' => 'a']);
    Item::factory()->create(['amount' => 9, 'name' => 'c']);

    // What components/table/column.blade.php now emits for <atom:table.column
    // sort="raw:amount * -1">, and what the Alpine click handler round-trips
    // through $wire.set — a legitimate raw sort must still work end to end.
    $signed = TableSort::sign('amount * -1');

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', $signed)
        ->set('_table.sort.direction', 'asc');

    $test->assertOk();

    $names = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('name')->all());

    expect($names)->toBe(['c', 'b', 'a']); // amount 9, 5, 1 → amount * -1 ascending
});

it('rejects a signed raw: sort whose signature was tampered with', function () {
    $signed = TableSort::sign('amount');

    // Flip one hex character of the hmac — same shape, wrong signature.
    $tampered = preg_replace('/^raw:./', 'raw:'.($signed[4] === '0' ? '1' : '0'), $signed);

    Livewire::test(TableFixture::class)
        ->set('_table.sort.column', $tampered)
        ->assertForbidden();
});

it('trusts an unsigned raw: sort set from PHP, not through the client', function () {
    Item::factory()->create(['amount' => 5]);
    Item::factory()->create(['amount' => 1]);
    Item::factory()->create(['amount' => 9]);

    $test = Livewire::test(TableFixture::class);

    // Mutating the property directly (not via ->set()) never touches
    // the update listener's client gate — this is what a host's own
    // mount() assigning $_table['sort']['column'] = 'raw:...' looks like.
    $test->instance()->_table['sort']['column'] = 'raw:amount * -1';
    $test->instance()->_table['sort']['direction'] = 'asc';

    $amounts = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('amount')->all());

    expect($amounts)->toBe([9, 5, 1]); // amount * -1 ascending
});

it('clamps a hostile sort direction on the raw: branch to asc', function () {
    Item::factory()->count(2)->create();

    // Direction carries no signature at all — a client can set it to
    // anything via $wire.set('_table.sort.direction', ...), so the raw:
    // branch must not trust it any further than asc/desc either way.
    // Mutated directly (not via ->set()) purely to keep this test to one
    // request; the client path for this property is otherwise ungated.
    $test = Livewire::test(TableFixture::class);
    $test->instance()->_table['sort']['column'] = 'raw:id';
    $test->instance()->_table['sort']['direction'] = 'asc; DROP TABLE items; --';

    DB::enableQueryLog();
    withLivewireContext($test->instance(), fn ($c) => $c->tableQuery()->toTable());
    $log = collect(DB::getQueryLog())->pluck('query')->implode(' ; ');
    DB::disableQueryLog();

    expect($log)->toContain('order by id asc')
        ->and($log)->not->toContain('DROP TABLE');
});

/**
 * Review finding (medium): the gate above only checked is_string($sortColumn)
 * on the resolved value, so an array-valued column — or a deeper path like
 * "_table.sort.column.0" that turns the leaf into an array — sailed straight
 * through the sort gate and then hit str($sortColumn) in toTable(),
 * an unauthenticated 500 rather than a 403 on any sortable table.
 */

it('refuses an array value for the sort column, not just a bad raw: string', function () {
    Livewire::test(TableFixture::class)
        ->set('_table.sort.column', ['raw:(SELECT 1)'])
        ->assertForbidden();
});

it('refuses a deeper write under _table.sort.column', function () {
    Livewire::test(TableFixture::class)
        ->set('_table.sort.column.0', 'raw:(SELECT 1)')
        ->assertForbidden();
});

it('refuses a wholesale _table.sort update carrying an unsigned raw column', function () {
    Livewire::test(TableFixture::class)
        ->set('_table.sort', ['column' => 'raw:(SELECT 1)', 'direction' => 'asc'])
        ->assertForbidden();
});

it('refuses a wholesale _table update carrying an unsigned raw column', function () {
    Livewire::test(TableFixture::class)
        ->set('_table', [
            'sort' => ['column' => 'raw:(SELECT 1)', 'direction' => 'asc'],
            'checkboxes' => [],
            'select_all' => false,
            'max_rows' => 100,
            'show_trashed' => false,
            'show_selected' => false,
        ])
        ->assertForbidden();
});

it('refuses a non-string sort direction', function () {
    Livewire::test(TableFixture::class)
        ->set('_table.sort.direction', ['asc'])
        ->assertForbidden();
});


/**
 * Review finding (medium): toTable() read `_table` from any component that had the
 * property, WithTable or not. A host's own public $_table has no sort
 * gate, so a client could set an unsigned raw: column that then read as PHP-set and
 * trusted: the #54 injection through a component that never opted in.
 */
it('reads the table state only from a component that uses WithTable', function () {
    Item::factory()->count(2)->create();

    $test = Livewire::test(OwnTableFixture::class)
        ->set('_table.sort.column', 'raw:(SELECT 1)')
        ->set('_table.sort.direction', 'desc');

    // nothing gates the host's own property, so the value did land
    expect($test->get('_table.sort.column'))->toBe('raw:(SELECT 1)');

    DB::enableQueryLog();
    withLivewireContext($test->instance(), fn () => Item::query()->toTable());
    $log = collect(DB::getQueryLog())->pluck('query')->implode(' ; ');
    DB::disableQueryLog();

    expect($log)->not->toContain('(SELECT 1)')
        ->and($log)->toContain('order by "id" desc');
});

it('applies no sort and the default row count to a component with no table state at all', function () {
    Item::factory()->count(3)->create();

    $component = new class extends \Livewire\Component {
        public function render() { return '<div></div>'; }
    };

    DB::enableQueryLog();
    $rows = withLivewireContext($component, fn () => Item::query()->toTable());
    $log = collect(DB::getQueryLog())->pluck('query')->implode(' ; ');
    DB::disableQueryLog();

    expect($rows->perPage())->toBe(100)
        ->and($log)->toContain('order by "id" desc');
});

it('still sorts by the table state of a component that uses WithTable', function () {
    Item::factory()->create(['name' => 'b']);
    Item::factory()->create(['name' => 'a']);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'name')
        ->set('_table.sort.direction', 'asc');

    $names = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('name')->all());

    expect($names)->toBe(['a', 'b']);
});

/**
 * Review finding (medium): the gate used to be a trait hook, and a method of the same
 * name on the host (Livewire's ordinary updating hook for a `$withTable` property)
 * silently replaced it, while toTable() still trusted _table because the component uses
 * WithTable. It is a listener on Livewire's `update` event now: no host method touches it.
 */
it('still refuses an unsigned raw: sort when the host defines updatingWithTable() itself', function () {
    Item::factory()->count(2)->create();

    $shadow = \Jiannius\Atom\Tests\Fixtures\SortGateShadowFixture::class;

    Livewire::test($shadow)->set('_table.sort.column', 'raw:(SELECT 1)')->assertForbidden();
    Livewire::test($shadow)->set('_table.sort', ['column' => 'raw:(SELECT 1)', 'direction' => 'asc'])->assertForbidden();
    Livewire::test($shadow)->set('_table', ['sort' => ['column' => 'raw:(SELECT 1)', 'direction' => 'asc']])->assertForbidden();
    Livewire::test($shadow)->set('_table.sort.column.0', 'raw:(SELECT 1)')->assertForbidden();

    $test = Livewire::test($shadow);

    DB::enableQueryLog();
    withLivewireContext($test->instance(), fn () => Item::query()->toTable());
    $log = collect(DB::getQueryLog())->pluck('query')->implode(' ; ');
    DB::disableQueryLog();

    expect($log)->not->toContain('(SELECT 1)');
});

it('refuses the magic $set too', function () {
    // $wire.$set() reaches the server as an ordinary update
    Livewire::test(TableFixture::class)->call('$set', '_table.sort.column', 'raw:(SELECT 1)')->assertForbidden();
});

it('leaves a legitimate update to the table state alone', function () {
    Livewire::test(TableFixture::class)
        ->set('_table.max_rows', 50)
        ->set('_table.sort.column', 'name')
        ->set('_table.sort.direction', 'desc')
        ->assertOk()
        ->assertSet('_table.max_rows', 50);
});

it('refuses the upload endpoint naming the sort column as its target', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    config()->set('livewire.temporary_file_upload.disk', 'local');

    // WithFileUploads writes the upload into whatever property the browser names, through
    // the same update event: a file is no string
    Livewire::test(\Jiannius\Atom\Tests\Fixtures\ComponentHelpersFixture::class)
        ->set('_table.sort.column', \Illuminate\Http\UploadedFile::fake()->image('a.jpg'))
        ->assertForbidden();
});
