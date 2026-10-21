<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Jiannius\Atom\Traits\WithTable;
use Livewire\WithPagination;
use Jiannius\Atom\Traits\WithUrlFilters;
use Livewire\Component;

/**
 * Drives the tests for the WithUrlFilters trait: a listing whose filters live in
 * the URL, and a record page below it (the same component, told which by mount()),
 * so the E2E can filter, open a row and come back through the breadcrumb.
 *
 * Rows are a static array rather than a model (the served testbench app has no
 * database). `tenant` is internal state the host sets and the browser must never
 * touch: a row of another tenant is never listed.
 */
class UrlFiltersFixture extends Component
{
    use WithBreadcrumbs;
    use WithTable;
    use WithPagination;
    use WithUrlFilters;

    /** @var array<string, mixed> */
    public array $filters = ['search' => '', 'status' => [], 'region' => 'MY', 'tenant' => 7];

    /** Whether this is the record page rather than the listing. */
    public bool $record = false;

    /** @var array<int, array{name: string, status: string, region: string, tenant: int}> */
    public const ROWS = [
        ['name' => 'Apple', 'status' => 'fresh', 'region' => 'MY', 'tenant' => 7],
        ['name' => 'Apricot', 'status' => 'dried', 'region' => 'MY', 'tenant' => 7],
        ['name' => 'Banana', 'status' => 'fresh', 'region' => 'SG', 'tenant' => 7],
        ['name' => 'Blueberry', 'status' => 'dried', 'region' => 'SG', 'tenant' => 7],
        ['name' => 'Cherry', 'status' => 'fresh', 'region' => 'MY', 'tenant' => 7],
        ['name' => 'Secret', 'status' => 'fresh', 'region' => 'MY', 'tenant' => 9],
    ];

    /**
     * The filters that live in the URL.
     *
     * @return array<string, mixed>
     */
    protected function urlFilters(): array
    {
        return ['search' => '', 'status' => [], 'region' => 'MY'];
    }

    /**
     * Internal state: set by the host, never by the browser.
     *
     * @return list<string>
     */
    protected function urlFiltersExcept(): array
    {
        return ['tenant'];
    }

    /**
     * The app-specific check: a region the listing knows, or "All" ('').
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        return $key !== 'region' || in_array($value, ['MY', 'SG', ''], true);
    }

    /**
     * Whether this is the record page.
     */
    public function mount(): void
    {
        $this->record = request()->is('atom/e2e/url-filters/record');
    }

    /**
     * A listing (the trail root) and a record page below it. The declared URLs carry
     * no query string, so the filters can only come back through the href captured
     * on navigation.
     */
    public function breadcrumbs($trail)
    {
        $trail->home('Listing', url('/atom/e2e/url-filters'));

        if (request()->is('atom/e2e/url-filters/record')) {
            $trail->push('Record', url('/atom/e2e/url-filters/record'));
        }

        return $trail;
    }

    /**
     * The rows of the tenant that match the filters. Stands in for the Eloquent
     * version, `Item::query()->filter($this->filters)`.
     *
     * @return array<int, array{name: string, status: string, region: string, tenant: int}>
     */
    public function getRowsProperty(): array
    {
        $filters = $this->filters;

        return array_values(array_filter(
            self::ROWS,
            fn ($row) => $row['tenant'] === ($filters['tenant'] ?? null)
                && (blank($filters['search'] ?? null) || str_contains(strtolower($row['name']), strtolower((string) $filters['search'])))
                && (blank($filters['status'] ?? null) || in_array($row['status'], (array) $filters['status'], true))
                && (blank($filters['region'] ?? null) || $row['region'] === $filters['region']),
        ));
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::url-filters-fixture');
    }
}
