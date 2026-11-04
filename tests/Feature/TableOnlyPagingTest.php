<?php

use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableOnlyFixture;
use Livewire\Livewire;
use Livewire\WithPagination;

// Issue #150: a paginated <atom:table> in a component that lists WithTable and nothing else threw
// MethodNotFoundException when the page changed. WithTable now uses WithPagination itself.

it('pages a toTable() list in a component that only uses WithTable', function () {
    expect(class_uses_recursive(TableOnlyFixture::class))->toHaveKey(WithPagination::class)
        ->and(class_uses(TableOnlyFixture::class))->not->toHaveKey(WithPagination::class);

    foreach (range(1, 7) as $i) {
        Item::create(['name' => "Item {$i}", 'status' => 'draft', 'amount' => $i]);
    }

    Livewire::test(TableOnlyFixture::class, ['database' => true])
        ->set('_table.max_rows', 3)
        ->assertSee('Item 1')->assertSee('Item 3')->assertDontSee('Item 4')
        ->call('nextPage')
        ->assertSetStrict('paginators.page', 2)
        ->assertSee('Item 4')->assertSee('Item 6')->assertDontSee('Item 3')->assertDontSee('Item 7')
        ->call('gotoPage', 3)
        ->assertSee('Item 7')->assertDontSee('Item 6')
        ->call('previousPage')
        ->assertSee('Item 4')->assertSee('Item 6');
});

it('pages the rows the pager of a WithTable-only component links to', function () {
    Livewire::test(TableOnlyFixture::class)
        ->assertSee('Row 10')->assertDontSee('Row 11')
        ->assertSeeHtml('wire:click="gotoPage(2')
        ->call('gotoPage', 2)
        ->assertSee('Row 11')->assertSee('Row 20')->assertDontSee('Row 10')->assertDontSee('Row 21');
});
