<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class DateRangeMorphFixture extends Component
{
    use AtomComponent;

    public ?string $date = null;

    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the range picker
     * (a wire:ignore'd tree) — the trigger the smgdms report blamed for a duplicated
     * calendar pair.
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
        return view('atom-test::date-range-morph');
    }
}
