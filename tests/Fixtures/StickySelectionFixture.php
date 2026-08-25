<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Drives the E2E for <atom:table :sticky-selection>: tick rows, narrow the list
 * with a search, tick another, and the earlier ticks are still there.
 *
 * Rows are a static array rather than a model — the point under test is that the
 * ids survive the Livewire round-trip a filter change triggers, which needs no
 * database.
 */
class StickySelectionFixture extends Component
{
    use AtomComponent;

    public string $search = '';

    /** How many ids the last bulk action saw. */
    public ?int $reported = null;

    /** @var array<int,array{id:int,name:string}> */
    public const ROWS = [
        ['id' => 1, 'name' => 'Apple'],
        ['id' => 2, 'name' => 'Apricot'],
        ['id' => 3, 'name' => 'Banana'],
        ['id' => 4, 'name' => 'Blueberry'],
        ['id' => 5, 'name' => 'Cherry'],
        ['id' => 6, 'name' => 'Cranberry'],
    ];

    /**
     * The rows to list: the selection while "show selected" is on, otherwise the
     * ones matching the current search. Stands in for the Eloquent version,
     * `$this->tableRowsQuery()->toTable()`.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function getRowsProperty(): array
    {
        $ids = $this->getTableCheckboxes();

        if ($this->isTableShowSelected() && $ids) {
            return array_values(array_filter(self::ROWS, fn ($row) => in_array($row['id'], $ids)));
        }

        if ($this->search === '') {
            return self::ROWS;
        }

        return array_values(array_filter(
            self::ROWS,
            fn ($row) => str_contains(strtolower($row['name']), strtolower($this->search)),
        ));
    }

    /**
     * Stand in for a bulk action, recording how many ids it was handed.
     */
    public function report(): void
    {
        $this->reported = count($this->getTableCheckboxes());
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::sticky-selection-fixture');
    }
}
