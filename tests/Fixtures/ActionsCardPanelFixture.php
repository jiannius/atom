<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ActionsCardPanelFixture extends Component
{
    /** @var array<int, string> every server call, in order, for the spec to read */
    public array $log = [];

    public int $renders = 0;

    /** Whether the extra action is in the card: a morph that adds an action while the menu is open */
    public bool $extra = false;

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
     * Add the extra action to the card on the next render.
     */
    public function addExtra(): void
    {
        $this->extra = true;
    }

    /**
     * Re-render the component without changing it.
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
        return view('atom-test::actions-card-panel');
    }
}
