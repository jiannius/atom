<?php

namespace Jiannius\Atom\Tests\Fixtures;

/**
 * A table that renders its trashed toggle, so the update gate lets the browser set `_table.show_trashed`.
 */
class TableTrashedFixture extends TableFixture
{
    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-trashed-fixture');
    }
}
