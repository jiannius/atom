<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A form.modal with stacked sections and the pinned save bar, opened as a slide-over: the E2E scrolls it, checks the bar stays at
 * the foot, and submits through the modal's own form (which calls submit()).
 */
class SlideOverFormFixture extends Component
{
    public int $saves = 0;

    public string $name = '';

    /**
     * The modal form's submit method.
     */
    public function submit(): void
    {
        $this->saves++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::slide-over-form-fixture');
    }
}
