<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A tile group with a Livewire child inside it (TileChildFixture), beside one tile of its own.
 */
class TileParentFixture extends Component
{
    /**
     * Render the parent's view.
     */
    public function render()
    {
        return view('atom-test::tile-parent');
    }
}
