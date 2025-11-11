<?php

namespace Jiannius\Atom\Macros;

class Request
{
    /**
     * Get the portal of the current request
     */
    public function portal()
    {
        return function ($is = null) {
            $route = $this->route()?->getName();

            if (in_array($route, [
                'login',
                'logout',
                'register',
                'password.forgot',
                'password.reset',
            ])) {
                $portal = 'auth';
            }
            else if ($route) {
                $portal = collect(explode('.', $route))->first();
                if (str($portal)->startsWith('__') || in_array($portal, ['socialite'])) $portal = null;
            }
            else $portal = null;

            if ($is && $portal) return $portal === $is;

            return $portal;
        };
    }

    /**
     * Get the subdomain of the current request
     */
    public function subdomain()
    {
        return function () {
            $segments = collect(explode('.', $this->host()));
            $segments->pop(2);

            return $segments->join('.') ?: null;
        };
    }

    /**
     * Get the host without the subdomain
     */
    public function hostWithoutSubdomain()
    {
        return function () {
            return collect(explode('.', $this->host()))->sortKeysDesc()->take(2)->sortKeys()->join('.');
        };
    }

    /**
     * Check if the current request is a Livewire request
     */
    public function isLivewireRequest()
    {
        return function () {
            return app(\Livewire\LivewireManager::class)->isLivewireRequest();
        };
    }
}