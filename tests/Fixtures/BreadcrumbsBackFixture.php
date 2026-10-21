<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsBackFixture extends Component
{
    use WithBreadcrumbs;

    /**
     * A listing (the trail root) and a record page below it. The declared URLs are
     * absolute and carry no query string, like a host's `route()` calls do, so the
     * listing's filters can only come back through the href captured on navigation.
     */
    public function breadcrumbs($trail)
    {
        $trail->home('Listing', url('/atom/e2e/breadcrumbs-back'));

        if (request()->is('atom/e2e/breadcrumbs-back/record')) {
            $trail->push('Record', url('/atom/e2e/breadcrumbs-back/record'));
        }

        return $trail;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-back-page');
    }
}
