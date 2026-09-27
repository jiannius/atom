<?php

use Jiannius\Atom\Services\TableSort;
use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Issue #54 — toTable()'s "raw:" sort had no allow-list.
 *
 * _table is a plain public Livewire property (Traits\AtomComponent), so a
 * client could $wire.set('_table.sort.column', 'raw:<anything>') and
 * toTable() forwarded the substring after "raw:" straight into
 * orderByRaw(), regardless of the sort= attributes declared on
 * <atom:table.column> — boolean-blind SQL injection via row order.
 *
 * Fixed with a signed token: components/table/column.blade.php signs a
 * declared raw: sort (Services\TableSort::sign()) before it ever reaches the
 * browser, and AtomComponent::updatingAtomComponent() refuses (403) any
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
    // updatingAtomComponent()'s client gate — this is what a host's own
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
