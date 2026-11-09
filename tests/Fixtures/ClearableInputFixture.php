<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ClearableInputFixture extends Component
{
    public ?string $plain = null;

    public ?string $live = null;

    public ?string $blur = null;

    public ?string $lazy = null;

    public ?string $stopped = null;

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::clearable-input');
    }
}
