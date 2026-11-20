<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;

/**
 * Drives the E2E for the morph of the two pagers: one component draws `<atom:pagination>` and `<atom:table.pagination>` over a paginator
 * that goes from a single page to many and back (`show('one')` / `show('many')`), so the `@if` blocks that hold the page links and the
 * rows menu are added and removed by the morph.
 */
class PagerMorphFixture extends Component
{
    use WithTable;

    public string $set = 'one';

    /**
     * Show a single page of rows, or many pages.
     */
    public function show(string $set): void
    {
        $this->set = $set;
        $this->resetPage();
    }

    /**
     * The paginator of the current set, 20 a page.
     */
    public function pager(): LengthAwarePaginator
    {
        $total = $this->set === 'many' ? 200 : 3;

        return new LengthAwarePaginator(collect(range(1, min(20, $total))), $total, 20, $this->getPage(), ['path' => '/']);
    }

    /**
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::pager-morph-fixture', ['pager' => $this->pager()]);
    }
}
