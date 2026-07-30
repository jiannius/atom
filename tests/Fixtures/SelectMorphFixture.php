<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class SelectMorphFixture extends Component
{
    use AtomComponent;

    public ?string $country = null;

    public ?string $status = null;

    public ?string $customer = null;

    public ?string $priority = null;

    /** @var array<int,string> */
    public array $tags = [];

    public ?int $record = null;

    public int $renders = 0;

    /**
     * Re-render the component so Livewire morphs the markup around the selects —
     * the trigger for the "listbox stops loading its options" bug.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Populate + open the already-mounted modal, the way a consuming app's
     * create()/edit() action does: the modal markup renders with the page, so this
     * re-render morphs into the pickers inside it rather than inserting them.
     */
    public function edit(int $record): void
    {
        $this->record = $record;
        $this->customer = 'MY';   // an already-picked record, as an edit form has
        $this->tags = ['a', 'c'];
        $this->modal('form')->slide();
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::select-morph');
    }
}
