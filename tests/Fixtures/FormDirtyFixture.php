<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithEditor;
use Livewire\Component;

/**
 * A page form with a save bar that carries the "Unsaved changes" label: a text field, an atom select, an atom date picker, a field
 * that syncs on every keystroke (`.live`), and a save that fails validation while the name is empty.
 */
class FormDirtyFixture extends Component
{
    use WithEditor;

    public ?string $name = 'Acme';

    // preset values: none of them may light the label at load
    public ?string $region = 'north';

    public ?string $zone = 'a';

    public ?string $due = '2026-10-15';

    public ?string $note = 'preset';

    public ?string $body = null;

    /** @var array<int, array{label: string}> A repeater: a button adds a row, another removes one. */
    public array $rows = [['label' => 'One']];

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
     * Add a row to the repeater.
     */
    public function addRow(): void
    {
        $this->rows[] = ['label' => 'Row '.(count($this->rows) + 1)];
    }

    /**
     * Remove the last row of the repeater.
     */
    public function removeRow(): void
    {
        array_pop($this->rows);
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
