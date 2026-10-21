<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A host with a `$withTable` flag and Livewire's ordinary updating hook for it. The
 * method has the name a trait hook would have, so it must not be able to stand in for,
 * or replace, the raw: sort gate.
 */
class SortGateShadowFixture extends Component
{
    use WithPagination;
    use WithTable;

    public bool $withTable = false;

    /**
     * The host's own hook for its flag
     */
    public function updatingWithTable($value)
    {
        // host: reset paging when the toggle flips
    }

    /**
     * Render the fixture
     */
    public function render()
    {
        return '<div>ok</div>';
    }
}
