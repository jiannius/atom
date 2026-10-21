<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Jiannius\Atom\Traits\WithEditor;
use Jiannius\Atom\Traits\WithRecaptcha;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every atom trait at once, and a method for each helper macro, so a test can
 * prove the helpers work server-side and are unreachable from the browser.
 */
class ComponentHelpersFixture extends Component
{
    use WithBreadcrumbs;
    use WithEditor;
    use WithPagination;
    use WithRecaptcha;
    use WithTable;

    /** @var mixed */
    public $result = null;

    /**
     * Fire a toast from PHP
     */
    public function notify(): void
    {
        $this->toast('Saved');
    }

    /**
     * Run an action from PHP
     */
    public function runAction(): void
    {
        $this->result = $this->action('open', ['method' => 'secret']);
    }

    /**
     * Run the spy action from PHP
     */
    public function runSpy(): void
    {
        $this->result = $this->action('spy');
    }

    /**
     * Render a Blade view that calls a helper through $this
     */
    public function render()
    {
        return view('atom-test::component-helpers-fixture');
    }
}
