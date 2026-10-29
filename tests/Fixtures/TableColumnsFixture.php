<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Drives the E2E for column visibility on <atom:table>: a paginated Livewire table with a checkbox column, a locked column, three
 * hideable ones (one hidden by default, one with a host's `max-sm:hidden`, one with `hidden lg:table-cell`) and a footer. The shape
 * is read from the query string: ?renamed=1 (email is called mail), ?removed=1 (no phone column), ?skeleton=1 (a skeleton until
 * `load()`), ?nokey=1 (no persist-key), ?state={"email":false} (the host's `columns-state`), ?transition=1 (wire:transition around
 * the table), ?empty=1 (no rows).
 */
class TableColumnsFixture extends Component
{
    use WithPagination;
    use WithTable;

    public const TOTAL = 30;

    public const SIZE = 5;

    public bool $loaded = true;

    public bool $renamed = false;

    public bool $removed = false;

    public bool $keyed = true;

    public bool $transition = false;

    public bool $empty = false;

    /** The table's wire:key: a new one makes Livewire draw a new table, which takes the server's `state` again */
    public int $generation = 0;

    public ?string $search = null;

    /** The host's copy of the user's choices (name => visible), or null for none */
    public ?array $state = null;

    /** @var list<array<string, mixed>> what the change event delivered, newest last */
    public array $events = [];

    /**
     * Read the shape from the query string.
     */
    public function mount(): void
    {
        $this->loaded = ! request()->boolean('skeleton');
        $this->renamed = request()->boolean('renamed');
        $this->removed = request()->boolean('removed');
        $this->keyed = ! request()->boolean('nokey');
        $this->transition = request()->boolean('transition');
        $this->empty = request()->boolean('empty');
        $this->_table['max_rows'] = self::SIZE;

        if (request()->has('state')) {
            $this->state = json_decode((string) request()->query('state'), true);
        }
    }

    /**
     * The skeleton gives way to the rows.
     */
    public function load(): void
    {
        $this->loaded = true;
    }

    /**
     * The server changes `columns-state` and nothing else: the table already on the page ignores it.
     */
    public function pushState(): void
    {
        $this->state = ['phone' => true];
    }

    /**
     * The server changes `columns-state` and the table's wire:key (a "reset layout" button, a saved-view switch): a new table.
     */
    public function resetLayout(): void
    {
        $this->state = ['phone' => true];
        $this->generation++;
    }

    /**
     * What a host does with the change event: keep the user's choices (here only a list the page shows).
     *
     * @param  array<string, bool>  $columns
     */
    public function saveColumns(array $columns): void
    {
        $this->events[] = $columns;
        $this->state = $columns;
    }

    /**
     * The page of rows, hand-built so the fixture needs no database.
     */
    public function items(): ?LengthAwarePaginator
    {
        if (! $this->loaded) {
            return null;
        }

        $page = Paginator::resolveCurrentPage();
        $rows = Collection::times(self::TOTAL, fn ($i) => ['id' => $i, 'name' => 'Person '.$i])
            ->filter(fn ($row) => blank($this->search) || str_contains(strtolower($row['name']), strtolower($this->search)))
            ->when($this->empty, fn ($rows) => collect());

        return new LengthAwarePaginator($rows->forPage($page, self::SIZE)->values(), $rows->count(), self::SIZE, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    /**
     * Render the fixture view.
     */
    public function render(): View
    {
        return view('atom-test::table-columns-fixture', ['items' => $this->items()]);
    }
}
