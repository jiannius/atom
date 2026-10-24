<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsHeaderFixture extends Component
{
    use WithBreadcrumbs;

    public bool $loaded = false;

    public string $page = 'old';

    /**
     * Keep the page the URL named: an update request is not on that URL.
     */
    public function mount(): void
    {
        $this->page = (string) request()->segment(4);
    }

    /**
     * Show the late page's page.header, as a Livewire update does (an `@if ($record)`, a lazy component).
     */
    public function load(): void
    {
        $this->loaded = true;
    }

    /**
     * Five pages, one trail each: `old` has one crumb and no page.header (the old pattern), `new` has one crumb and a
     * page.header (the v4 pattern), `plain` has two crumbs and no page.header, `deep` has two crumbs and a page.header, `late` has one crumb and gets its page.header from a Livewire action.
     */
    public function breadcrumbs($trail)
    {
        $page = $this->page ?: (string) request()->segment(4);

        $trail->home(['old' => 'Old page', 'new' => 'New page', 'plain' => 'Reports', 'deep' => 'Reports', 'late' => 'Late page'][$page], url('/atom/e2e/breadcrumbs-header/'.$page));

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
        return view('atom-test::breadcrumbs-header-page', ['page' => $this->page]);
    }
}
