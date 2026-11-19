<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class SelectPlaceholderFixture extends Component
{
    public ?string $plain = null;

    public ?string $live = null;

    /** @var array<int, string> */
    public array $multi = [];

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::select-placeholder');
    }
}
