<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for a row that opens an `href` (#207). It is a Livewire component so the row holds atom's real checkbox cell
 * (`<atom:table.cell checkbox>` reads `$wire._table`) beside `<atom:table.actions>`, a `wire:click` and an `x-on:click` control.
 */
class TableRowHrefFixture extends Component
{
    use WithPagination;
    use WithTable;

    public int $pings = 0;

    /**
     * Count a click on the `wire:click` control, so a spec can tell it ran.
     */
    public function ping(): void
    {
        $this->pings++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-row-href-fixture');
    }
}
