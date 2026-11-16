<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Drives the E2E for `<atom:action>` in a table row's ⋯ menu: rows that open an `href`, each with the same actions.
 */
class ActionRowsFixture extends Component
{
    /** @var array<int, string> every server call, in order, for the spec to read */
    public array $log = [];

    public int $renders = 0;

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
        return view('atom-test::action-rows');
    }
}
