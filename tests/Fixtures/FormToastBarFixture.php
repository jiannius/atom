<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A page form with a pinned save bar: a failing submit raises the form's error toast, a passing one the app's own Saved toast.
 * `$bar` off swaps the bar for a plain footer, the baseline for the toast's place.
 */
class FormToastBarFixture extends Component
{
    public ?string $name = null;

    public bool $bar = true;

    /** The toast inside this component instead of beside it: a morph then reaches the toast's own root. */
    public bool $toastInside = false;

    /** A page that fits the screen, so the bar is not pinned at the bottom of it. */
    public bool $short = false;

    public int $saves = 0;

    public int $touches = 0;

    /**
     * Validate the form; a pass raises the app's own "Saved" toast.
     */
    public function save(): void
    {
        $this->validate(['name' => 'required'], ['name.required' => 'Name is required.']);

        $this->saves++;
        $this->toast('Saved');
    }

    /**
     * A round trip that is not a submit, so it morphs and changes nothing the toast reads.
     */
    public function touch(): void
    {
        $this->touches++;
    }

    /**
     * Swap the save bar for the plain footer, and back: a bar that comes and goes through a morph.
     */
    public function toggleBar(): void
    {
        $this->bar = ! $this->bar;
    }

    /**
     * Take the toast that sits inside this component away, and bring it back.
     */
    public function toggleToast(): void
    {
        $this->toastInside = ! $this->toastInside;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::form-toast-bar');
    }
}
