<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class BreadcrumbsFixture extends Component
{
    use AtomComponent;

    /**
     * A single-crumb trail — the shape where the breadcrumb IS the visible page
     * heading, so losing the trail loses the page title too.
     */
    public function breadcrumbs($trail)
    {
        return $trail->home('Dashboard', '/atom/e2e/breadcrumbs');
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-page');
    }
}
