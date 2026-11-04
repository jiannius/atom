<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ChartMorphFixture extends Component
{
    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the charts.
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
        return view('atom-test::chart-morph');
    }
}
