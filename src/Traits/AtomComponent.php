<?php

namespace Jiannius\Atom\Traits;

use Livewire\WithFileUploads;
use Livewire\WithPagination;

trait AtomComponent
{
    use WithPagination;
    use WithFileUploads;

    public $_breadcrumbs = [];

    public $_table = [
        'sort' => ['column' => null, 'direction' => null],
        'checkboxes' => [],
        'select_all' => false,
        'max_rows' => 100,
        'show_trashed' => false,
        'show_selected' => false,
    ];

    public $_editor = [
        'images' => [],
    ];

    public $_recaptcha = null;

    /**
     * Mount the atom component
     */
    public function mountAtomComponent()
    {
        if (method_exists($this, 'breadcrumbs')) {
            $this->_breadcrumbs = $this->breadcrumbs(app('atom')->breadcrumbs())->build();
        }
    }

    /**
     * Update the atom component
     */
    public function updatedAtomComponent($property, $value)
    {
        // we generate a temporary preview url for editor images upload
        // upon persist the editor content to the model, it will then search all the temporary url
        // and store it in the disk (model need to use the AsEditorContent casts to the desired column)
        // in case the editor content is not persisted (eg, user decided not to save the content)
        // the temporary upload will just go through the normal purging process by livewire
        if ($property === '_editor.images') {
            $images = [];

            foreach ($value as $upload) {
                $images[] = $upload->temporaryUrl();
            }

            $this->fill(['_editor.images' => $images]);
        }

        // A trashed-view toggle changes which rows are listed, so a lingering
        // checkbox selection would point at rows no longer shown. Clear it.
        if ($property === '_table.show_trashed') {
            $this->resetTableCheckboxes();
        }
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
     *   #[Computed] public function items() { return $this->tableRows()->toTable(); }
     *
     * Falls back to the filtered list when the flag outlives the selection it was
     * showing (the user unticked the last row), which would otherwise leave the
     * table empty with no visible way back.
     */
    public function tableRows()
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
        $this->resetPage();
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

    /**
     * Generate a wire key
     */
    public function wirekey(...$args)
    {
        return $args
            ? md5(json_encode(array_filter($args)))
            : md5((string) str()->ulid());
    }

    /**
     * Show modal in front end
     */
    public function modal($name = null)
    {
        return app('atom')->modal($name ?? app('livewire')->current()->getName());
    }

    /**
     * Show command palette in front end
     */
    public function command($name = null)
    {
        return app('atom')->command($name ?? app('livewire')->current()->getName());
    }

    /**
     * Show toast in front end
     */
    public function toast(...$args)
    {
        app('atom')->toast(...$args);
    }

    /**
     * Show alert in front end
     */
    public function alert(...$args)
    {
        app('atom')->alert(...$args);
    }

    /**
     * Show confirm in front end
     */
    public function confirm(...$args)
    {
        app('atom')->confirm(...$args);
    }

    /**
     * Trigger action
     */
    public function action($name, $params = [], $render = false)
    {
        if (!$render) $this->skipRender();

        return app('atom')->action($name, $params);
    }

    /**
     * Verify the reCAPTCHA token carried by <atom:form recaptcha>.
     * Throws a validation error when the token resolves to a bot; fails open
     * (does nothing) when recaptcha is not configured or cannot be reached.
     */
    public function verifyRecaptcha(?string $action = null, ?float $minScore = null) : void
    {
        app('atom')->recaptcha()->verify($this->_recaptcha, $action, $minScore);
    }
}