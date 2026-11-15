<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A page with an island: the form is outside it (`outside`) or inside it (`inside`). An island is rendered again on its own by the same component, so
 * what its fields draw must not depend on a form that is only around them the first time.
 */
class MarkerIslandFixture extends Component
{
    public string $where = 'outside';

    /**
     * Render the page's view.
     */
    public function render()
    {
        return view('atom-test::marker-island-'.$this->where);
    }
}
