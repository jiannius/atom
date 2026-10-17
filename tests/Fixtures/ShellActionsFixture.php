<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ShellActionsFixture extends Component
{
    public int $count = 0;

    /**
     * Re-render the component so Livewire morphs the header's actions slot.
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
        return view('atom-test::shell-actions');
    }
}
