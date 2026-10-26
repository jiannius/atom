<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for the table's filter bar and its toolbar (beta.10): the add pills that turn into editable chips, the More
 * filters button and its count, the row count at the top end and the pinned pager, all over a real Livewire round trip.
 *
 * 128 generated rows, filtered by status, tag and search; the issued range is held but does not filter. `bump()` changes
 * nothing the table shows, so a spec can force a morph while a chip menu is open. `limit` cuts the rows, for a table
 * of one page, `count` is the toolbar's prop and `plain` drops the paginator.
 */
class TableFilterBarFixture extends Component
{
    use WithPagination;
    use WithTable;

    /** Rows behind the paginator with no filter. */
    public const TOTAL = 128;

    /** @var array<string, mixed> */
    public array $filters = ['search' => '', 'status' => null, 'tags' => [], 'issued' => null, 'region' => null];

    /** Bumped by a button: a round trip that changes nothing in the table. */
    public int $ticks = 0;

    /** The rows are cut to this many when set, for the one-page case. */
    public ?int $limit = null;

    /** The toolbar's `count` prop. */
    public bool $count = true;

    /** A table with no paginator, over the first page's rows. */
    public bool $plain = false;

    /**
     * Fifty rows a page, as the spec of the row count reads ("1–50 of 128").
     */
    public function mount(): void
    {
        $this->_table['max_rows'] = 50;
    }

    /**
     * A filter changes the result set, so the page goes back to the first.
     */
    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    /**
     * A round trip that renders the same table.
     */
    public function bump(): void
    {
        $this->ticks++;
    }

    /**
     * The rows that pass the filters, a page of them.
     */
    public function items(): LengthAwarePaginator
    {
        $maxRows = (int) data_get($this->_table, 'max_rows', 50);
        $page = Paginator::resolveCurrentPage();
        $search = strtolower((string) $this->filters['search']);
        $tags = (array) $this->filters['tags'];

        $rows = Collection::times($this->limit ?? self::TOTAL, fn ($i) => [
            'id' => $i,
            'name' => 'Item '.$i,
            'status' => ['open', 'closed', 'hold'][$i % 3],
            'tag' => ['a', 'b', 'c', 'd'][$i % 4],
        ])
            ->when($this->filters['status'], fn ($rows, $status) => $rows->where('status', $status))
            ->when($tags, fn ($rows) => $rows->whereIn('tag', $tags))
            ->when($search !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(strtolower($row['name']), $search)))
            ->values();

        return new LengthAwarePaginator(
            $rows->forPage($page, $maxRows)->values(),
            $rows->count(),
            $maxRows,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::table-filter-bar-fixture', ['items' => $this->items()]);
    }
}
