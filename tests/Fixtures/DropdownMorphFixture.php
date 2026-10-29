<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class DropdownMorphFixture extends Component
{
    public ?string $slow = null;

    public ?string $plain = null;

    public ?string $filter = null;

    public int $renders = 0;

    public string $menuClass = 'menu-a';

    public int $generation = 0;

    public bool $showSwap = true;

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
     * Give the swap probe's trigger a new key, so the morph replaces that node and leaves the tooltip around it.
     */
    public function swap(): void
    {
        $this->generation++;
    }

    /**
     * Take the swap probe out of the page, so Alpine destroys its tooltip.
     */
    public function removeSwap(): void
    {
        $this->showSwap = false;
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
