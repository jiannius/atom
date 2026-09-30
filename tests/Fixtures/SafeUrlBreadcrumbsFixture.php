<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class SafeUrlBreadcrumbsFixture extends Component
{
    use AtomComponent;

    /**
     * A trail with a hostile URL in it, as a host would build one from a record's
     * user-supplied link. `?penultimate=hostile` puts it where back() navigates to.
     */
    public function breadcrumbs($trail)
    {
        $hostile = 'javascript:window.__pwned = 1';

        if (request()->query('penultimate') === 'hostile') {
            return $trail->home('Home', '/atom/e2e/safe-url-breadcrumbs')
                ->push('Legit', '/atom/e2e/safe-url-breadcrumbs?legit=1')
                ->push('Hostile', $hostile)
                ->push('Current', url()->current());
        }

        return $trail->home('Home', '/atom/e2e/safe-url-breadcrumbs')
            ->push('Hostile', $hostile)
            ->push('Legit', '/atom/e2e/safe-url-breadcrumbs?legit=1')
            ->push('Current', url()->current());
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-page');
    }
}
