<?php

namespace Jiannius\Atom\Traits;

/**
 * Feeds <atom:breadcrumbs>: a component that defines a breadcrumbs() method
 * gets its trail built at mount into $_breadcrumbs, which the front end reads.
 *
 *   public function breadcrumbs($breadcrumbs) { return $breadcrumbs->home('Items', '/items')->push('Edit'); }
 */
trait WithBreadcrumbs
{
    public $_breadcrumbs = [];

    /**
     * Build the breadcrumbs trail
     */
    public function mountWithBreadcrumbs()
    {
        if (method_exists($this, 'breadcrumbs')) {
            $this->_breadcrumbs = $this->breadcrumbs(app('atom')->breadcrumbs())->build();
        }
    }
}
