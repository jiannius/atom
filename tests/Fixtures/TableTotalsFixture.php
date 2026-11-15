<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\View\View;
use Livewire\Component;

/**
 * Drives the E2E for totals lines on a Livewire table: a plain table (no WithTable) with hideable columns and three totals lines that
 * follow `amount`. `bump()` re-renders the component and changes the subtotal, so a spec can tell the morph happened.
 */
class TableTotalsFixture extends Component
{
    public int $bumps = 0;

    /**
     * A server round trip that changes what the totals print.
     */
    public function bump(): void
    {
        $this->bumps++;
    }

    /**
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::table-totals-fixture');
    }
}
