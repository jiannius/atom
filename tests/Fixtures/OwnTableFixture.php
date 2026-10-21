<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A host component with a public $_table of its own and no WithTable: nothing gates
 * the property, so whatever the browser sets must never be read as the table's sort.
 */
class OwnTableFixture extends Component
{
    /** @var array<string, mixed> */
    public $_table = ['sort' => ['column' => null, 'direction' => null]];

    /**
     * Render the fixture
     */
    public function render()
    {
        return '<div>ok</div>';
    }
}
