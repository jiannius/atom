<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class AccountMenuFixture extends Component
{
    public int $count = 0;

    /**
     * Change the state the page shows, so a click that reaches a live component is visible.
     */
    public function bump(): void
    {
        $this->count++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::account-menu');
    }
}
