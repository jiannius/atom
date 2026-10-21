<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class DatePickerTypedFixture extends Component
{
    public ?string $date = null;

    public ?string $deferredDate = null;

    public ?string $dateTime = null;

    public ?string $range = null;

    public ?string $deferredRange = null;

    public int $saves = 0;

    /**
     * Round-trip the deferred models, so the e2e can read what reached the server.
     */
    public function save(): void
    {
        $this->saves++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::date-picker-typed');
    }
}
