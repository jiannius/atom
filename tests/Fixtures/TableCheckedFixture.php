<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for the checked bar's `tone="accent"`: a table with one row ticked, so the bar is showing. The query string picks
 * the shape (`frameless`, `tone`, `sticky`), so one page covers the framed and the frameless bar, tinted and not, with every text
 * role the bar can print (the count, "select all", "show selected", "clear selection") on screen.
 */
class TableCheckedFixture extends Component
{
    use WithPagination;
    use WithTable;

    public bool $frameless = false;

    public ?string $tone = null;

    public bool $sticky = false;

    /** @var array<int, array{id: int, name: string}> */
    public const ROWS = [
        ['id' => 1, 'name' => 'Apple'],
        ['id' => 2, 'name' => 'Banana'],
        ['id' => 3, 'name' => 'Cherry'],
    ];

    /**
     * Read the shape from the query string and tick the first row.
     */
    public function mount(): void
    {
        $this->frameless = request()->boolean('frameless');
        $this->tone = request()->query('tone') ?: null;
        $this->sticky = request()->boolean('sticky');
        $this->_table['checkboxes'] = [1];
    }

    /**
     * A paginator that says there are more rows than the page holds, so "select all" is offered.
     */
    public function getPagerProperty(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(self::ROWS, 12, 3, 1);
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-checked-fixture');
    }
}
