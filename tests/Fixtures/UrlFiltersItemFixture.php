<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Jiannius\Atom\Traits\WithUrlFilters;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * WithUrlFilters over a real model, so a test can see what the filter() macro would
 * have done with a key the allow-list lets through: a named scope (`status`) and a
 * plain column (`amount:>=`) are both reachable by filter() and neither is declared.
 */
class UrlFiltersItemFixture extends Component
{
    use WithPagination;
    use WithTable;
    use WithUrlFilters;

    /** @var array<string, mixed> */
    public array $filters = ['search' => ''];

    /**
     * Only the search is declared.
     *
     * @return array<string, mixed>
     */
    protected function urlFilters(): array
    {
        return ['search' => ''];
    }

    /**
     * The filtered query.
     */
    public function tableQuery()
    {
        return Item::query()->filter($this->filters);
    }

    /**
     * Render nothing: the test reads tableQuery().
     */
    public function render()
    {
        return '<div></div>';
    }
}
