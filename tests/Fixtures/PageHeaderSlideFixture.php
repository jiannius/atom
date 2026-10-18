<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class PageHeaderSlideFixture extends Component
{
    public int $count = 0;

    public bool $running = true;

    /**
     * Re-render the component, so Livewire morphs the header inside the open slide-over.
     */
    public function bump(): void
    {
        $this->count++;
    }

    /**
     * Change the status the header shows, so the morph has something to patch.
     */
    public function finish(): void
    {
        $this->running = false;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::page-header-slide');
    }
}
