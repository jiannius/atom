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
     * Build the breadcrumbs trail, and tell the layout how long it is: the page mounts before the layout renders, so a
     * layout can leave out a top bar that a one-crumb trail above a page.header would leave empty (the first writer is the
     * page; a nested component's trail is not). On the request, not in a static, so it does not outlive it under Octane.
     */
    public function mountWithBreadcrumbs()
    {
        if (method_exists($this, 'breadcrumbs')) {
            $this->_breadcrumbs = $this->breadcrumbs(app('atom')->breadcrumbs())->build();

            $attributes = request()->attributes;

            if (! $attributes->has(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE)) {
                $trail = $this->_breadcrumbs;
                $attributes->set(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE, filled($trail['home'] ?? null) ? 1 + count($trail['items'] ?? []) : 0);
            }
        }
    }
}
