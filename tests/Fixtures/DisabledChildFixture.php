<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A Livewire child rendered inside a disabled <atom:form> (the corpus case `livewire-child`): a child's own update renders it alone, with no
 * form around it in that request, so whether it keeps its read-only state after an update is the question this exists to ask.
 */
class DisabledChildFixture extends Component
{
    public ?string $v = 'child';

    public int $renders = 0;

    /**
     * Re-render this child on its own.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Render the child's view.
     */
    public function render()
    {
        return view('atom-test::disabled-child');
    }
}
