<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A component whose view throws, leaving Livewire's render stack unpopped.
 */
class RenderThrowsFixture extends Component
{
    /**
     * Render a view that aborts
     */
    public function render()
    {
        return '<div>{{ abort(404) }}</div>';
    }
}
