<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * UrlFiltersFixture whose own mount() leaves a key nobody declared in the filters,
 * and a hostile value in a declared one, as a host that wrote either would.
 */
class UrlFiltersStrayFixture extends UrlFiltersFixture
{
    /**
     * Plant the stray key and a bad region, after the URL has been read.
     */
    public function mount(): void
    {
        parent::mount();

        $this->filters['stray'] = 'x';
        $this->filters['region'] = 'XX';
    }
}
