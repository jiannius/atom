<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A form that marks the optional fields, with a Livewire child inside it. The child is rendered with the form's data on the Blade stack at first, and alone
 * after its own update, so what it draws must not depend on the form.
 */
class MarkerParentFixture extends Component
{
    /**
     * Render the parent's view.
     */
    public function render()
    {
        return view('atom-test::marker-parent');
    }
}
