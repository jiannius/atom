<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsTrailFixture extends Component
{
    use WithBreadcrumbs;

    /**
     * Every page the trail fixture serves, with its title. A page's trail is the pages from `dashboard` down to it (the
     * `deep` chain), `two` is a trail of its own (two crumbs) and `reports` its one-crumb home, and `long` is a trail of five whose last crumb has a very long title.
     */
    public const DEEP = [
        'dashboard' => 'Dashboard',
        'sales' => 'Sales and Marketing Operations',
        'regions' => 'Regional Distribution Centres',
        'asia-pacific' => 'Asia Pacific and Oceania',
        'customers' => 'Customer Accounts Receivable',
        'northwind' => 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur',
        'invoices' => 'Outstanding Invoices Register',
        'invoice' => 'Invoice 1042',
    ];

    public const LONG = 'Quarterly Consolidated Statement of Financial Position and Comprehensive Income for the Group';

    public string $page = 'dashboard';

    /**
     * Keep the page the URL named: an update request is not on that URL.
     */
    public function mount(): void
    {
        $this->page = (string) request()->segment(4);
    }

    /**
     * The trail of the page the URL names: `two` has two crumbs, a page of the deep chain has one more than the one above it.
     */
    public function breadcrumbs($trail)
    {
        $page = $this->page ?: (string) request()->segment(4);
        $url = fn (string $slug) => url('/atom/e2e/breadcrumbs-trail/'.$slug);

        if ($page === 'reports') {
            return $trail->home('Reports', $url('reports'));
        }

        if ($page === 'two') {
            return $trail->home('Reports', $url('reports'))->push('Monthly', $url('two'));
        }

        if ($page === 'long') {
            return $trail->home('Dashboard', $url('dashboard'))
                ->push(self::DEEP['sales'], $url('sales'))
                ->push(self::DEEP['regions'], $url('regions'))
                ->push(self::DEEP['asia-pacific'], $url('asia-pacific'))
                ->push(self::LONG, $url('long'));
        }

        $trail->home('Dashboard', $url('dashboard'));

        if ($page === 'dashboard') {
            return $trail;
        }

        foreach (array_slice(self::DEEP, 1, null, true) as $slug => $title) {
            $trail->push($title, $url($slug));

            if ($slug === $page) {
                break;
            }
        }

        return $trail;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::breadcrumbs-trail-page', ['pages' => [...array_keys(self::DEEP), 'reports', 'two', 'long']]);
    }
}
