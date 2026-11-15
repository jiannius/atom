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
 * Drives the E2E for the card's heading row (`actions` and `menu` slots) and <atom:panel>: a card and a panel, each with a ⋯ menu
 * that re-renders the component (`bump()`), and in the panel a frameless table of 128 rows, fifty a page, so the pinned pager has
 * something to stick to. `wide` makes the table wider than the panel, `menus` is false for a heading row with no ⋯.
 */
class CardPanelFixture extends Component
{
    use WithPagination;
    use WithTable;

    public const TOTAL = 128;

    /** Bumped by a menu item: a round trip that changes nothing the page shows but the counter. */
    public int $ticks = 0;

    /** Twelve more columns, so the table is wider than the panel and scrolls sideways. */
    public bool $wide = false;

    /** False draws the headings with no ⋯ menu, to compare the row's height with and without one. */
    public bool $menus = true;

    /** A host's own layout on the card: grid, flex, space3 or space6 (see the view). */
    public ?string $host = null;

    /**
     * Fifty rows a page.
     */
    public function mount(): void
    {
        $this->_table['max_rows'] = 50;
    }

    /**
     * A round trip that renders the same page.
     */
    public function bump(): void
    {
        $this->ticks++;
    }

    /**
     * A page of the rows.
     */
    public function items(): LengthAwarePaginator
    {
        $maxRows = (int) data_get($this->_table, 'max_rows', 50);
        $page = Paginator::resolveCurrentPage();
        $rows = Collection::times(self::TOTAL, fn ($i) => ['id' => $i, 'name' => 'Item '.$i, 'status' => ['open', 'closed', 'hold'][$i % 3]]);

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
        return view('atom-test::card-panel-fixture', ['items' => $this->items()]);
    }
}
