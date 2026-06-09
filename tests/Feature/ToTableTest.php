<?php

use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\HandleComponents;

/**
 * Helper: call a #[Computed] method on a Livewire component instance while
 * the component is on the Livewire stack so that toTable() can read $_table
 * via app('livewire')->current().
 *
 * app('livewire')->current() calls last(HandleComponents::$componentStack),
 * which returns false (not null) when the stack is empty — the ?-> operator
 * does not protect against false, so we must push/pop the component manually.
 */
function withLivewireContext(object $component, callable $callback): mixed
{
    array_push(HandleComponents::$componentStack, $component);
    try {
        return $callback($component);
    } finally {
        array_pop(HandleComponents::$componentStack);
    }
}

it('defaults to latest id ordering when unsorted', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $test = Livewire::test(TableFixture::class);
    $page = withLivewireContext($test->instance(), fn ($c) => $c->items());

    expect($page->first()->id)->toBe($b->id); // latest('id')
});

it('sorts by column + direction from $_table', function () {
    Item::factory()->create(['amount' => 5]);
    Item::factory()->create(['amount' => 1]);
    Item::factory()->create(['amount' => 9]);

    $test = Livewire::test(TableFixture::class)
        ->set('_table.sort.column', 'amount')
        ->set('_table.sort.direction', 'asc');

    $amounts = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('amount')->all());

    expect($amounts)->toBe([1, 5, 9]);
});

it('shows only trashed when show_trashed is true', function () {
    $live = Item::factory()->create();
    $trashed = Item::factory()->create();
    $trashed->delete();

    $test = Livewire::test(TableFixture::class)->set('_table.show_trashed', true);
    $ids = withLivewireContext($test->instance(), fn ($c) => $c->items()->pluck('id')->all());

    expect($ids)->toBe([$trashed->id]);
});

it('paginates by max_rows', function () {
    Item::factory()->count(5)->create();

    $test = Livewire::test(TableFixture::class)->set('_table.max_rows', 2);

    $paginator = withLivewireContext($test->instance(), fn ($c) => $c->items());

    expect($paginator->perPage())->toBe(2)
        ->and($paginator->count())->toBe(2);
});

it('resetTableCheckboxes empties the selection', function () {
    $component = Livewire::test(TableFixture::class)
        ->set('_table.checkboxes', [1, 2, 3])
        ->call('resetTableCheckboxes');

    expect($component->get('_table.checkboxes'))->toBe([]);
});
