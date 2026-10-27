<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A page form with a save bar that carries the "Unsaved changes" label: a text field, an atom select, an atom date picker, a field
 * that syncs on every keystroke (`.live`), and a save that fails validation while the name is empty.
 */
class FormDirtyFixture extends Component
{
    public ?string $name = 'Acme';

    public ?string $region = null;

    public ?string $zone = null;

    public ?string $due = null;

    public ?string $note = null;

    public bool $dirty = true;

    public int $saves = 0;

    public int $touches = 0;

    /**
     * Validate and "save".
     */
    public function save(): void
    {
        $this->validate(['name' => 'required'], ['name.required' => 'Name is required.']);

        $this->saves++;
    }

    /**
     * A round trip that is not a save: it morphs the form and changes nothing in it.
     */
    public function touch(): void
    {
        $this->touches++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::form-dirty');
    }
}
