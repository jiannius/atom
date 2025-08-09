<?php

namespace Jiannius\Atom\Traits;

use Livewire\WithPagination;

trait AtomComponent
{
    use WithPagination;

    public $_breadcrumbs = [];

    public $_table = [
        'sort' => ['column' => null, 'direction' => null],
        'checkboxes' => [],
        'max_rows' => 100,
        'show_trashed' => false,
    ];

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
     * Show modal in front end
     */
    public function modal($name = null)
    {
        return app('atom')->modal($name ?? app('livewire')->current()->getName());
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
}