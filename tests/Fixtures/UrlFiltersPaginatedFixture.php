<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A listing that paginates under its own page name ('search' by default, so it
 * collides with a declared filter). Livewire registers a page name in `$paginators`
 * only when the paginator is built, which is during render, after mount.
 */
class UrlFiltersPaginatedFixture extends UrlFiltersFixture
{
    /** The page name the host's paginator uses. */
    public static string $pageName = 'search';

    /**
     * Build the paginator the way a host's `->paginate(10, pageName: ...)` does.
     */
    public function render()
    {
        new LengthAwarePaginator(self::ROWS, count(self::ROWS), 2, null, ['pageName' => static::$pageName]);

        return parent::render();
    }
}
