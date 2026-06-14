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
     * The full scoped + filtered query (no pagination) — backs both the table
     * paginator and $this->tableSelection() for cross-page select-all.
     */
    public function tableQuery()
    {
        return Item::query()->filter(array_filter($this->filters));
    }

    /**
     * The paginated items for the table.
     */
    #[Computed]
    public function items()
    {
        return $this->tableQuery()->toTable();
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-fixture');
    }
}
