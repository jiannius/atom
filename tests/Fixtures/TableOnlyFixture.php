<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;

/**
 * A host list that uses WithTable and nothing else (no WithPagination), the shape issue #150 reported: a paginated
 * <atom:table> whose page changes. Rows are generated, so the E2E needs no database; `$database` swaps them for the
 * Item model and toTable(), as a host's list reads them.
 */
class TableOnlyFixture extends Component
{
    use WithTable;

    /** Rows behind the paginator. */
    public const TOTAL = 25;

    /** Read the rows from the Item model through toTable() instead of generating them. */
    public bool $database = false;

    /**
     * Ten rows a page.
     */
    public function mount(): void
    {
        $this->_table['max_rows'] = 10;
    }

    /**
     * The rows of the page the table is on.
     */
    public function items(): LengthAwarePaginator
    {
        $maxRows = (int) data_get($this->_table, 'max_rows', 10);

        if ($this->database) {
            return Item::query()->orderBy('id')->toTable();
        }

        $page = Paginator::resolveCurrentPage();
        $rows = Collection::times(self::TOTAL, fn ($i) => ['id' => $i, 'name' => 'Row '.$i, 'amount' => $i]);

        return new LengthAwarePaginator(
            $rows->forPage($page, $maxRows)->values()->map(fn ($row) => (object) $row),
            $rows->count(),
            $maxRows,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Render the shared table view over this component's page.
     */
    public function render(): View
    {
        return view('atom-test::table-only-fixture', ['items' => $this->items()]);
    }
}
