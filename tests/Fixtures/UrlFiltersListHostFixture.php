<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A host that judges a list filter as a whole: it refuses a list with "rotten" in
 * it, a list that holds both "fresh" and "dried", and throws on a "boom" item.
 */
class UrlFiltersListHostFixture extends UrlFiltersFixture
{
    /**
     * The whole list is what this host sees for a list filter.
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        if ($key === 'status' && is_array($value)) {
            if (in_array('boom', $value, true)) {
                throw new \InvalidArgumentException('could not parse boom');
            }

            return ! in_array('rotten', $value, true)
                && ! (in_array('fresh', $value, true) && in_array('dried', $value, true));
        }

        return parent::isUrlFilterValid($key, $value);
    }
}
