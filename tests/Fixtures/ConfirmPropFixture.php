<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ConfirmPropFixture extends Component
{
    /** @var array<int, string> every server call, in order, for the spec to read */
    public array $log = [];

    public string $value = '';

    public int $renders = 0;

    /**
     * Record a call with its argument.
     */
    public function record(string $name = ''): void
    {
        $this->log[] = $name;
    }

    /**
     * A call that takes a moment, so the button shows its loading state.
     */
    public function slow(): void
    {
        usleep(700000);
        $this->log[] = 'slow';
    }

    /**
     * What a delete with no action of its own calls once the dialog accepts.
     */
    public function delete(): void
    {
        $this->log[] = 'delete';
    }

    /**
     * What the form's submit button calls.
     */
    public function save(): void
    {
        $this->log[] = 'save';
    }

    /**
     * Re-render the component, the way a host's own update does while a dialog is open.
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
        return view('atom-test::confirm-prop');
    }
}
