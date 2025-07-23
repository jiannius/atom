<?php

namespace Jiannius\Atom\Macros;

class Route
{
    /**
     * Get the name of the current route, including in livewire update request
     */
    public function current()
    {
        return function () {
            if (request()->route()->named('livewire.update')) {
                $previousUrl = url()->previous();
                $previousRoute = app('router')->getRoutes()->match(request()->create($previousUrl));
                return $previousRoute->getName();
            } else {
                return request()->route()->getName();
            }
        };
    }
}