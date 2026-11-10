<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A Livewire child given `:disabled` by its parent (the corpus case `livewire_child`). A child's own update renders it alone, with no parent or
 * form around it, so a disabled `<atom:form>` cannot reach it then: a child takes its own `:disabled`, and this holds that it keeps it after an update.
 */
class DisabledChildFixture extends Component
{
    public ?string $v = 'child';

    public bool $disabled = false;

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
