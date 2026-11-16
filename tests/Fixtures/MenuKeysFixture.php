<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class MenuKeysFixture extends Component
{
    /** @var array<int, string> every server call, in order, for the spec to read */
    public array $log = [];

    /** @var array<int, string> the keyed items of the "keyed" menu, in order */
    public array $order = ['a', 'b', 'c'];

    public int $renders = 0;

    public int $generation = 0;

    /**
     * Record a call with its argument.
     */
    public function record(string $name = ''): void
    {
        $this->log[] = $name;
    }

    /**
     * What a delete with no action of its own calls once the dialog accepts.
     */
    public function delete(): void
    {
        $this->log[] = 'delete';
    }

    /**
     * Re-render the component, so Livewire morphs the markup around an open menu.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Reverse the keyed items, so the morph moves their nodes.
     */
    public function reorder(): void
    {
        $this->order = array_reverse($this->order);
    }

    /**
     * Give the keyed items new keys, so the morph replaces their nodes.
     */
    public function rekey(): void
    {
        $this->generation++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::menu-keys');
    }
}
