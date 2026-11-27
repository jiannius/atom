<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Drives the E2E for an in-field loader and the in-field icons: <atom:table.search> in a few shapes (plain, labelled,
 * with a caption and an error, taller, clearable), and the other controls that pin an icon inside the box, each at the
 * standard height and taller. Any Enter in a search calls $wire.$refresh(), which is what the spinner is scoped to.
 */
class InputLoaderFixture extends Component
{
    public ?string $clearable = null;

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::input-loader-fixture');
    }
}
