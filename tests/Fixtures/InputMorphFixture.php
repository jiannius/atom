<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class InputMorphFixture extends Component
{
    use AtomComponent;

    public ?string $search = null;

    public ?string $live = null;

    public ?string $phone = null;

    public ?string $notes = null;

    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the fields — the
     * trigger for the "every input is replaced instead of patched" bug.
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
        return view('atom-test::input-morph');
    }
}
