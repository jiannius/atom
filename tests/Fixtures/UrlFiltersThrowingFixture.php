<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A host check that throws on a value it cannot parse, as Carbon::parse('garbage')
 * does in the README's own example.
 */
class UrlFiltersThrowingFixture extends UrlFiltersFixture
{
    /**
     * Throw instead of returning false for a search that "does not parse".
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        if ($key === 'search' && $value === 'boom') {
            throw new \InvalidArgumentException('could not parse');
        }

        return parent::isUrlFilterValid($key, $value);
    }
}
