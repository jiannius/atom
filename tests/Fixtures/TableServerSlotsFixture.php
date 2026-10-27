<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A table whose `header` and `checked` slots hold server-rendered Blade that changes on the server: an `@if` on a property, a select
 * that depends on another select's value, and a note. Under Alpine 3.17 (Livewire 4.4) a `<template x-if>` around such a slot is
 * rendered once and the morph never touches it again, so none of it would update.
 */
class TableServerSlotsFixture extends Component
{
    use WithPagination;
    use WithTable;

    public bool $foo = false;

    public ?string $region = null;

    public ?string $city = null;

    public string $note = 'first note';

    public bool $sticky = false;

    /** @var array<int, array{id: int, name: string}> */
    public const ROWS = [
        ['id' => 1, 'name' => 'Apple'],
        ['id' => 2, 'name' => 'Banana'],
    ];

    /**
     * The cities of the picked region: what the dependent select offers.
     */
    public function getCitiesProperty(): array
    {
        return match ($this->region) {
            'north' => [['value' => 'n1', 'label' => 'Northtown'], ['value' => 'n2', 'label' => 'Northville']],
            'south' => [['value' => 's1', 'label' => 'Southtown']],
            default => [],
        };
    }

    /**
     * Read the shape from the query string.
     */
    public function mount(): void
    {
        $this->sticky = request()->boolean('sticky');
    }

    /**
     * A paginator that says there are more rows than the page holds, so "select all" is offered.
     */
    public function getPagerProperty(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(self::ROWS, 12, 2, 1);
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-server-slots');
    }
}
