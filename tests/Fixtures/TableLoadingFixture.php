<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Drives the E2E for <atom:table>'s loading overlay: enough rows for the table to
 * run well past the fold, and a deliberate server-side pause on every request
 * after the first, so the overlay is observable in a real browser.
 *
 * Rows are generated rather than read from a model — the overlay is front-end
 * only, so all this fixture owes the test is a real paginator and a slow response.
 */
class TableLoadingFixture extends Component
{
    use AtomComponent;

    /** Set at the end of the first render, so only later requests pause. */
    public bool $rendered = false;

    /** How long a request after the first takes to come back, in microseconds.
     *  Long enough to measure the overlay at several scroll offsets in one flight,
     *  and in the same range as the 2.5s round-trip the bug was reported against. */
    public const PAUSE = 1200000;

    /** Total rows behind the paginator — enough to page at every offered size. */
    public const TOTAL = 500;

    /**
     * The paginated rows, hand-built so the fixture needs no database.
     */
    public function items(): LengthAwarePaginator
    {
        $maxRows = (int) data_get($this->_table, 'max_rows', 100);
        $page = Paginator::resolveCurrentPage();

        $rows = Collection::times(self::TOTAL, fn ($i) => ['id' => $i, 'name' => 'Row '.$i]);

        return new LengthAwarePaginator(
            $rows->forPage($page, $maxRows)->values(),
            $rows->count(),
            $maxRows,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Render the fixture view, pausing on every request but the first.
     */
    public function render()
    {
        if ($this->rendered) {
            usleep(self::PAUSE);
        }

        $this->rendered = true;

        return view('atom-test::table-loading-fixture', ['items' => $this->items()]);
    }
}
