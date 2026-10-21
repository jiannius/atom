<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;

/**
 * The same table, in a component that has WithTable.
 */
class TraitGuardTableChildFixture extends TraitGuardChildFixture
{
    use WithTable;
}
