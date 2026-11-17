<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for `<atom:action>` in the table's two bars (design rules, phase 5): a table whose toolbar (`header` slot) and bulk bar (`checked`,
 * `checked-menu`) are written with actions only. The spec ticks rows and reads what each bar placed; `?tone=accent` tints the bulk bar.
 */
class ActionTableFixture extends Component
{
    use WithPagination;
    use WithTable;

    public ?string $tone = null;

    /** @var array<int, string> every server call, in order, for the spec to read */
    public array $log = [];

    public int $renders = 0;

    /** Whether the reminder action is in the bulk bar: a morph that adds an action while the ⋯ is open */
    public bool $reminder = false;

    /** @var array<int, array{id: int, name: string}> */
    public const ROWS = [
        ['id' => 1, 'name' => 'Apple'],
        ['id' => 2, 'name' => 'Banana'],
        ['id' => 3, 'name' => 'Cherry'],
    ];

    /**
     * Read the shape from the query string.
     */
    public function mount(): void
    {
        $this->tone = request()->query('tone') === 'accent' ? 'accent' : null;
    }

    /**
     * Record a call with its argument.
     */
    public function record(string $name = ''): void
    {
        $this->log[] = $name;
    }

    /**
     * What a delete with no action of its own calls once its dialog is accepted: the count it was asked about.
     */
    public function delete(): void
    {
        $this->log[] = 'delete:'.count($this->_table['checkboxes'] ?? []);
        $this->_table['checkboxes'] = [];
    }

    /**
     * Add the reminder action to the bulk bar on the next render.
     */
    public function addReminder(): void
    {
        $this->reminder = true;
    }

    /**
     * Re-render the view with nothing changed.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::action-table');
    }
}
