<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A host check that throws on a value it cannot parse, as Carbon::parse('garbage')
 * does in the README's own example, or fails on a type the host did not expect.
 */
class UrlFiltersThrowingFixture extends UrlFiltersFixture
{
    /**
     * Throw instead of returning false for a search that "does not parse".
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        if (is_string($value) && str_starts_with($value, 'boom')) {
            // quotes the value, as Carbon's own message does
            throw new \InvalidArgumentException('could not parse '.$value);
        }

        if ($key === 'search' && $value === 'type') {
            throw new \TypeError('a host bug');
        }

        return parent::isUrlFilterValid($key, $value);
    }
}
