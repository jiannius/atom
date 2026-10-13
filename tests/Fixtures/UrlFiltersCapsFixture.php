<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * Smaller caps than the defaults, to show a host can set its own.
 */
class UrlFiltersCapsFixture extends UrlFiltersFixture
{
    /**
     * Strings of 5 bytes at most.
     */
    protected function urlFilterMaxLength(): int
    {
        return 5;
    }

    /**
     * Lists of 3 items at most.
     */
    protected function urlFilterMaxItems(): int
    {
        return 3;
    }
}
