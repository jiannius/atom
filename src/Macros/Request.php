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
}
