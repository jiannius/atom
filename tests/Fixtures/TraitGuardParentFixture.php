<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Renders a child Livewire component, to prove the missing-trait check looks at the
 * component whose view is rendering, not at the parent.
 */
class TraitGuardParentFixture extends Component
{
    /** @var class-string */
    public string $child = '';

    /**
     * Render the child
     */
    public function render()
    {
        return '<div>@livewire($child)</div>';
    }
}
