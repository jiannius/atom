<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for `<atom:table rows-toggle-when-selected>` (#235): rows that open an `href` (plain and `wire:navigate`), one with a
 * `wire:click`, each with a checkbox cell and controls inside. The query string picks the shape: `off=1` leaves the prop off,
 * `sticky=1` adds `sticky-selection`, `all=1` starts in select-all mode, `ticked=1,2` starts with those rows ticked.
 */
class TableRowToggleFixture extends Component
{
    use WithPagination;
    use WithTable;

    public bool $off = false;

    public bool $sticky = false;

    public int $pings = 0;

    /**
     * Read the shape from the query string.
     */
    public function mount(): void
    {
        $this->off = request()->boolean('off');
        $this->sticky = request()->boolean('sticky');

        if (request()->boolean('all')) {
            $this->_table['select_all'] = true;
        }

        $ticked = array_filter(explode(',', (string) request()->query('ticked')));

        if ($ticked) {
            $this->_table['checkboxes'] = array_values($ticked); // strings, as the checkbox cell writes them
        }
    }

    /**
     * Count a click on a `wire:click` row or control, so a spec can tell it ran.
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
        return view('atom-test::table-row-toggle-fixture');
    }
}
