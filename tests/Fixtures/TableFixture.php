<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TableFixture extends Component
{
    use AtomComponent;

    /** @var array<string,mixed> */
    public array $filters = ['search' => null, 'status' => []];

    /**
     * The paginated items for the table.
     */
    #[Computed]
    public function items()
    {
        return Item::query()->toTable(array_filter($this->filters));
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-fixture');
    }
}
