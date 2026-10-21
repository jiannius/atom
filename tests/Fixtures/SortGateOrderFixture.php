<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;

/**
 * A host that saves the user's sort preference from Livewire's own updating hooks.
 * They run on the same event as the raw: sort gate and must never see a value it refuses.
 */
class SortGateOrderFixture extends Component
{
    use WithTable;

    /** @var list<array{0: string, 1: mixed}> */
    public static array $seen = [];

    /**
     * The generic hook
     */
    public function updating($name, $value)
    {
        static::$seen[] = [$name, $value];
    }

    /**
     * The hook for the _table property
     */
    public function updatingTable($value, $key)
    {
        static::$seen[] = [$key, $value];
    }

    /**
     * The hook for the sort column
     */
    public function updatingTableSortColumn($value)
    {
        static::$seen[] = ['_table.sort.column', $value];
    }

    /**
     * Render the fixture
     */
    public function render()
    {
        return '<div>ok</div>';
    }
}
