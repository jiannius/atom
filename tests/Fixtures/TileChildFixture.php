<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * The Livewire child of TileParentFixture: a lone radio and a lone checkbox. Inside the parent's tile group it is rendered with the group's data on the
 * Blade stack at first, and alone after its own update, so what it draws must not depend on the group.
 */
class TileChildFixture extends Component
{
    public int $renders = 0;

    /**
     * Re-render this child on its own.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Render the child's view.
     */
    public function render()
    {
        return view('atom-test::tile-child');
    }
}
