<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Drives the E2E for selectable tiles (`variant="tile"` on radio.group and checkbox.group): a radio group and a checkbox group bound with
 * wire:model.live, an Alpine one bound with x-model, and a round trip (`bump()`) that morphs the page without changing a choice.
 */
class TilesFixture extends Component
{
    public ?string $plan = 'growth';

    /** @var array<int, string> */
    public array $extras = ['express'];

    /** A round trip that renders the same page. */
    public int $ticks = 0;

    /**
     * A round trip that changes nothing the page shows but the counter.
     */
    public function bump(): void
    {
        $this->ticks++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::tiles');
    }
}
