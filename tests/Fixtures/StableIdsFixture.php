<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class StableIdsFixture extends Component
{
    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the controls: the trigger for
     * "an id minted per render replaces the node and drops its state".
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::stable-ids');
    }
}
