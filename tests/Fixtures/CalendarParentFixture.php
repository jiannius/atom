<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A page component that holds the calendar fixture as a child, with a button of its own: a request of the parent renders the parent, and Livewire's
 * morph leaves a child's root alone, so it says nothing about where the child's items are. `/atom/e2e/calendar?parent=1`.
 */
class CalendarParentFixture extends Component
{
    public int $clicks = 0;

    /**
     * A call of the page's own, that has nothing to do with the calendar.
     */
    public function poke(): void
    {
        $this->clicks++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::calendar-parent');
    }
}
