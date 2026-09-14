<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Drives the E2E for <atom:table>'s loading overlay: enough rows for the table to
 * run well past the fold.
 *
 * Rows are generated rather than read from a model — the overlay is front-end
 * only, so all this fixture owes the test is a real paginator. The spec holds the
 * response open itself, with page.route(), rather than sleeping here: `testbench
 * serve` is a single-worker php artisan serve, so a server-side pause blocks every
 * other Playwright worker sharing it and surfaces as an unrelated spec timing out.
 */
class TableLoadingFixture extends Component
{
    use AtomComponent;

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
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::table-loading-fixture', ['items' => $this->items()]);
    }
}
