<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Drives the E2E for <atom:table.search> inside <atom:table.filters>: a search
 * box beside a select filter, over a table, so "Clear all" has a Livewire round
 * trip to run against.
 *
 * Rows are a static array rather than a model (the served testbench app has no
 * database); the point under test is the chip and the clear, not the query.
 */
class TableSearchChipFixture extends Component
{
    use AtomComponent;

    #[Url]
    public ?string $search = null;

    public ?string $status = null;

    /** @var array<int,array{name:string,status:string}> */
    public const ROWS = [
        ['name' => 'Apple', 'status' => 'fresh'],
        ['name' => 'Apricot', 'status' => 'dried'],
        ['name' => 'Banana', 'status' => 'fresh'],
        ['name' => 'Blueberry', 'status' => 'dried'],
        ['name' => 'Cherry', 'status' => 'fresh'],
        ['name' => 'Cranberry', 'status' => 'dried'],
    ];

    /**
     * The rows matching the current search and status. Stands in for the
     * Eloquent version, `Item::query()->filter(...)->toTable()`.
     *
     * @return array<int,array{name:string,status:string}>
     */
    public function getRowsProperty(): array
    {
        return array_values(array_filter(
            self::ROWS,
            fn ($row) => (blank($this->search) || str_contains(strtolower($row['name']), strtolower($this->search)))
                && (blank($this->status) || $row['status'] === $this->status),
        ));
    }

    /**
     * Empty the search from the server, the way a host's `$this->reset('filters')` does.
     */
    public function resetSearch(): void
    {
        $this->reset('search');
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-search-chip-fixture');
    }
}
