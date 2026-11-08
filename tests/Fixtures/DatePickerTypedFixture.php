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

    /** The pickers' `timezone` prop, from `?tz=` (none by default), so the e2e can run them in a zone through a real re-render. */
    public ?string $tz = null;

    /**
     * Read the zone to run the pickers in from the page's query.
     */
    public function mount(): void
    {
        $tz = request()->query('tz');

        $this->tz = is_string($tz) && preg_match('~^[A-Za-z0-9_/+:-]{1,64}$~', $tz) ? $tz : null;
    }

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
