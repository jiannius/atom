<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A component with no atom trait that renders an <atom:table>.
 */
class TraitGuardChildFixture extends Component
{
    /**
     * Render a static table
     */
    public function render()
    {
        return '<div><atom:table><x-slot:rows></x-slot:rows></atom:table></div>';
    }
}
