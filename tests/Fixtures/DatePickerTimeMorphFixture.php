<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class DatePickerTimeMorphFixture extends Component
{
    use AtomComponent;

    public ?string $date = null;

    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the date picker —
     * the trigger the smgdms report blamed for wiping the open panel's calendar.
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
        return view('atom-test::date-picker-time-morph');
    }
}
