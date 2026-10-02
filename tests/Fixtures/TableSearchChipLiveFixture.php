<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * The table-search-chip fixture with the search bound `.live`, so the rows filter
 * as the user types and the chip is expected to follow them.
 */
class TableSearchChipLiveFixture extends TableSearchChipFixture
{
    public bool $live = true;
}
