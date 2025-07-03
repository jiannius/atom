<?php

namespace Jiannius\Atom\Traits;

trait AtomComponent
{
    public function bootAtomComponent()
    {
        if (method_exists($this, 'breadcrumbs')) {
            $this->breadcrumbs(app('atom')->breadcrumbs())->dispatch();
        }
    }

    public function modal($name)
    {
        return app('atom')->modal($name);
    }

    public function toast(...$args)
    {
        app('atom')->toast(...$args);
    }

    public function alert(...$args)
    {
        app('atom')->alert(...$args);
    }

    public function confirm(...$args)
    {
        app('atom')->confirm(...$args);
    }
}