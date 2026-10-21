<?php

namespace Jiannius\Atom\Traits;

use Jiannius\Atom\Services\TableSort;

/**
 * State and methods behind <atom:table>: sort, checkboxes, "select all
 * matching", max rows, trashed toggle. Add it to any Livewire component that
 * renders <atom:table> or sorts through the toTable() builder macro; the table
 * blades throw a clear error when it is missing.
 *
 * Pagination is Livewire's own: add WithPagination as well for a paginated table.
 */
trait WithTable
{
    public $_table = [
        'sort' => ['column' => null, 'direction' => null],
        'checkboxes' => [],
        'select_all' => false,
        'max_rows' => 100,
        'show_trashed' => false,
        'show_selected' => false,
    ];

    /**
     * Update the table state
     */
    public function updatedWithTable($property, $value)
    {
        // A trashed-view toggle changes which rows are listed, so a lingering
        // checkbox selection would point at rows no longer shown. Clear it.
        if (is_string($property) && $property === '_table.show_trashed') {
            $this->resetTableCheckboxes();
        }
    }

    /**
     * Guard the table's reserved state before a client-driven update
     * lands (Livewire calls this once per dotted path a $wire.set() targets).
     *
     * "_table.sort.column" is a plain public property, so a client can set it
     * to any "raw:" value it likes and toTable() would hand that straight to
     * orderByRaw() — issue #54. Refuse any update that would leave it holding
     * a "raw:" value with no valid signature; a signed one (produced by
     * components/table/column.blade.php) is let through unchanged. Also
     * refuse anything that would leave either leaf non-string (an array, or
     * a deeper path such as "_table.sort.column.0") — toTable() only expects
     * a string there, and a non-string used to reach str() and crash the
     * request (500) rather than being rejected (403).
     */
    public function updatingWithTable($name, $value)
    {
        // a host property named `withTable` calls this hook with its value for $name
        if (is_string($name) && $this->isUnsafeTableSortUpdate($name, $value)) {
            abort(403, 'Invalid table sort.');
        }
    }

    /**
     * Whether a client-driven update to $name (new value $value) would leave
     * "_table.sort.column" or "_table.sort.direction" unsafe: non-string, or
     * a string "raw:" column with no valid signature.
     */
    protected function isUnsafeTableSortUpdate($name, $value) : bool
    {
        // A path deeper than the leaf itself (e.g. "_table.sort.column.0")
        // can only turn that leaf into an array — no legitimate update ever
        // needs to reach this deep.
        if (str_starts_with($name, '_table.sort.column.') || str_starts_with($name, '_table.sort.direction.')) {
            return true;
        }

        $column = match ($name) {
            '_table.sort.column' => $value,
            '_table.sort' => data_get($value, 'column'),
            '_table' => data_get($value, 'sort.column'),
            default => null,
        };

        if ($column !== null && (!is_string($column) || TableSort::isUnsafeFromClient($column))) {
            return true;
        }

        $direction = match ($name) {
            '_table.sort.direction' => $value,
            '_table.sort' => data_get($value, 'direction'),
            '_table' => data_get($value, 'sort.direction'),
            default => null,
        };

        return $direction !== null && !is_string($direction);
    }

    /**
     * Get the checkboxes of the table
     */
    public function getTableCheckboxes()
    {
        return data_get($this->_table, 'checkboxes', []);
    }

    /**
     * Reset the checkboxes of the table (clears both modes)
     */
    public function resetTableCheckboxes()
    {
        $this->_table['checkboxes'] = [];
        $this->_table['select_all'] = false;
        $this->_table['show_selected'] = false;
    }

    /**
     * Drop "select all matching" but keep the checked ids — what a sticky-selection
     * table does on a filter change, since the flag is scoped to a query that no
     * longer exists while the ids still name real rows.
     */
    public function clearTableSelectAll()
    {
        $this->_table['select_all'] = false;
    }

    /**
     * Select every row matching the current table query (cross-page).
     * Stored as an intent flag, not an id list, so it scales to any size.
     */
    public function selectAllTableMatching()
    {
        $this->_table['select_all'] = true;
    }

    /**
     * Whether "select all matching" is active
     */
    public function isTableSelectAll()
    {
        return (bool) data_get($this->_table, 'select_all');
    }

    /**
     * The query targeting the current table selection — the whole filtered set
     * when "select all matching" is on, otherwise the checked ids resolved
     * against tableSelectionQuery(). Backs bulk actions:
     * $this->tableSelection()->delete(). Requires a tableQuery() method on the
     * component (the full scoped + filtered query).
     */
    public function tableSelection()
    {
        if (!method_exists($this, 'tableQuery')) {
            throw new \BadMethodCallException(static::class.' must define a tableQuery() method to use tableSelection().');
        }

        if ($this->isTableSelectAll()) {
            return $this->tableQuery();
        }

        return $this->tableSelectionQuery()->whereKey($this->getTableCheckboxes());
    }

    /**
     * The query backing the table's rows: the selection while "show selected" is
     * on, otherwise the normal filtered list. Render from this instead of
     * tableQuery() to get the toggle:
     *
     *   #[Computed] public function items() { return $this->tableRowsQuery()->toTable(); }
     *
     * Falls back to the filtered list when the flag outlives the selection it was
     * showing (the user unticked the last row), which would otherwise leave the
     * table empty with no visible way back.
     */
    public function tableRowsQuery()
    {
        if ($this->isTableShowSelected() && ($this->getTableCheckboxes() || $this->isTableSelectAll())) {
            return $this->tableSelection();
        }

        return $this->tableQuery();
    }

    /**
     * Whether the table is listing the selection rather than the filtered rows
     */
    public function isTableShowSelected()
    {
        return (bool) data_get($this->_table, 'show_selected');
    }

    /**
     * Flip between listing the selection and the filtered rows
     */
    public function toggleTableShowSelected()
    {
        $this->_table['show_selected'] = !$this->isTableShowSelected();

        // the selection ignores the filters, so the page it was on means nothing
        // on the other side of the flip
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * The base query the checked ids resolve against. Defaults to the filtered
     * query, so an unaware component behaves exactly as before.
     *
     * With <atom:table :sticky-selection> the ids outlive the filter that
     * produced them, and resolving them through tableQuery() would silently
     * intersect them with the *current* filter — the bar says 3 selected and
     * the bulk action hits 1. Such a component overrides this with its
     * scoped-but-unfiltered base:
     *
     *   public function tableSelectionQuery() { return Item::query(); }
     *   public function tableQuery() { return $this->tableSelectionQuery()->filter($this->filters); }
     *
     * Deliberately not defaulted to a bare newQuery() off the model: that would
     * make sticky work with no override, but only for apps whose tenancy is a
     * global scope. The consuming app is the only party that can safely say what
     * "unfiltered but still scoped" means.
     */
    public function tableSelectionQuery()
    {
        return $this->tableQuery();
    }

    /**
     * Check if the table is showing trashed
     */
    public function isTableShowTrashed()
    {
        return data_get($this->_table, 'show_trashed', false);
    }
}
