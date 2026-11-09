<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Three modals for the Escape specs (#174): `esc` holds every way the focus can leave a button, `outer` and `inner` open each other.
 * `?escapable=0`, `?outer=0` and `?inner=0` make the matching one non-escapable.
 */
class ModalEscapeFixture extends Component
{
    public bool $escapable = true;

    public bool $outerEscapable = true;

    public bool $innerEscapable = true;

    public bool $loaded = false;

    /**
     * Read which modals are escapable from the query string.
     */
    public function mount(): void
    {
        $this->escapable = request()->query('escapable') !== '0';
        $this->outerEscapable = request()->query('outer') !== '0';
        $this->innerEscapable = request()->query('inner') !== '0';
    }

    /**
     * A re-render that takes the focused button out of the page (a morph).
     */
    public function load(): void
    {
        $this->loaded = true;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::modal-escape');
    }
}
