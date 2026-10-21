<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A component with no atom trait that renders a table inside a lazy island.
 */
class IslandTableFixture extends Component
{
    /**
     * Render the fixture
     */
    public function render()
    {
        return view('atom-test::island-table-fixture');
    }
}
