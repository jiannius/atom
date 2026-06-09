<?php

use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Livewire\Livewire;

it('renders the table fixture with seeded rows', function () {
    Item::factory()->count(3)->create();

    Livewire::test(TableFixture::class)
        ->assertOk()
        ->assertSee('Name');
});
