<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * The Livewire child of MarkerParentFixture: one required and one other field.
 */
class MarkerChildFixture extends Component
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
        return view('atom-test::marker-child');
    }
}
