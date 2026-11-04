<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A component with no atom trait that renders an <atom:table> with a checked bar (the part that needs WithTable).
 */
class TraitGuardChildFixture extends Component
{
    /**
     * Render a static table
     */
    public function render()
    {
        return '<div><atom:table><x-slot:checked>Act</x-slot:checked><x-slot:rows></x-slot:rows></atom:table></div>';
    }
}
