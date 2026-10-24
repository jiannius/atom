<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A host whose isUrlFilterValid() reads null as "All" beside '', which is what clearing a filter pill sends.
 */
class UrlFiltersNullClearFixture extends UrlFiltersFixture
{
    /**
     * A region the listing knows, or "All" as '' or null.
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        return $key !== 'region' || in_array($value, ['MY', 'SG', '', null], true);
    }
}
