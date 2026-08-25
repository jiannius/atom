<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A table using <atom:table :sticky-selection> — the selection survives a
 * filter change, so the id branch of tableSelection() has to resolve against
 * the scoped-but-unfiltered base rather than the current filtered query.
 */
class StickyTableFixture extends TableFixture
{
    /**
     * The scoped base, without the user's filters.
     */
    public function tableSelectionQuery()
    {
        return Item::query();
    }

    /**
     * The full scoped + filtered query.
     */
    public function tableQuery()
    {
        return $this->tableSelectionQuery()->filter(array_filter($this->filters));
    }
}
