<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsUntrailedFixture extends Component
{
    use WithBreadcrumbs;

    // Deliberately declares NO breadcrumbs() method, so $_breadcrumbs stays [] —
    // the empty-payload path through the Alpine factory.

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-page');
    }
}
