<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A default computed in mount(), the way the docs say to write it: the filter is
 * declared null and filled with ??=, so the URL (read first) wins over it, and an
 * explicit "All" ('') is not mistaken for "no choice".
 */
class UrlFiltersMountDefaultFixture extends UrlFiltersFixture
{
    /** @var array<string, mixed> */
    public array $filters = ['search' => null, 'status' => [], 'region' => 'MY', 'tenant' => 7];

    /**
     * @return array<string, mixed>
     */
    protected function urlFilters(): array
    {
        return ['search' => null, 'status' => [], 'region' => 'MY'];
    }

    /**
     * Fill the default only when nothing chose a value.
     */
    public function mount(): void
    {
        parent::mount();

        $this->filters['search'] ??= 'this-month';
    }
}
