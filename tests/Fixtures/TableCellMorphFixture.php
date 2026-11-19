<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\View\View;
use Livewire\Component;

/**
 * Drives the E2E for the morph of a table cell (issue #263): a table whose rows are an UN-keyed `@foreach`, and a `$set` that swaps
 * the rows. In `full` every email is filled; in `blank` the same positions hold an empty cell, so a row with an empty cell (the
 * missing-value mark) takes the place of a row with a filled one. `$keyed` adds a `wire:key` to each row, and `$filler` gives the
 * email cell a filler of its own.
 */
class TableCellMorphFixture extends Component
{
    public string $set = 'full';

    public bool $keyed = false;

    public bool $filler = false;

    /**
     * Read the shape from the query string.
     */
    public function mount(): void
    {
        $this->keyed = request()->boolean('keyed');
        $this->filler = request()->boolean('filler');
    }

    /**
     * Show another set of rows.
     */
    public function show(string $set): void
    {
        $this->set = $set;
    }

    /**
     * The rows of the current set: an email, or null for an empty cell.
     *
     * @return array<int, array{id: int, name: string, email: ?string}>
     */
    public function rows(): array
    {
        return match ($this->set) {
            'blank' => [
                ['id' => 1, 'name' => 'Row one', 'email' => null],
                ['id' => 2, 'name' => 'Row two', 'email' => null],
                ['id' => 3, 'name' => 'Row three', 'email' => null],
            ],
            'mixed' => [
                ['id' => 1, 'name' => 'Row one', 'email' => null],
                ['id' => 2, 'name' => 'Row two', 'email' => 'two@example.test'],
            ],
            default => [
                ['id' => 1, 'name' => 'Row one', 'email' => 'one@example.test'],
                ['id' => 2, 'name' => 'Row two', 'email' => 'two@example.test'],
                ['id' => 3, 'name' => 'Row three', 'email' => 'three@example.test'],
            ],
        };
    }

    /**
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::table-cell-morph-fixture', ['rows' => $this->rows()]);
    }
}
