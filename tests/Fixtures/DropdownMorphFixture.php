<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class DropdownMorphFixture extends Component
{
    use AtomComponent;

    public ?string $slow = null;

    public ?string $plain = null;

    public ?string $filter = null;

    public int $renders = 0;

    public string $menuClass = 'menu-a';

    /**
     * Re-render the component so Livewire morphs the markup around every open panel.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Change a server-rendered attribute on the dropdown root, so the spec can show
     * the root still takes a server update.
     */
    public function restyle(): void
    {
        $this->menuClass = 'menu-b';
    }

    /**
     * A `.live` field whose response lands late, the way a slow request does: the
     * user has opened the next field by the time the morph arrives.
     */
    public function updatedSlow(): void
    {
        usleep(600000);
        $this->renders++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::dropdown-morph');
    }
}
