<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsHeaderFixture extends Component
{
    use WithBreadcrumbs;

    /**
     * Four pages, one trail each: `old` has one crumb and no page.header (the old pattern), `new` has one crumb and a
     * page.header (the v4 pattern), `plain` has two crumbs and no page.header, `deep` has two crumbs and a page.header.
     */
    public function breadcrumbs($trail)
    {
        $page = request()->segment(4);

        $trail->home(['old' => 'Old page', 'new' => 'New page', 'plain' => 'Reports', 'deep' => 'Reports'][$page], url('/atom/e2e/breadcrumbs-header/'.$page));

        if (in_array($page, ['plain', 'deep'], true)) {
            $trail->push('Detail', url('/atom/e2e/breadcrumbs-header/'.$page.'/detail'));
        }

        return $trail;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-header-page', ['page' => request()->segment(4)]);
    }
}
