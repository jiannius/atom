<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class SelectMorphFixture extends Component
{
    use AtomComponent;

    public ?string $country = null;

    public ?string $status = null;

    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the selects —
     * the trigger for the "listbox stops loading its options" bug.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::select-morph');
    }
}
