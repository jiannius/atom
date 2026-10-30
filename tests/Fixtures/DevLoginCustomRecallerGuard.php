<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Auth\SessionGuard;

/**
 * A session guard whose remember-me cookie does not follow the `remember_<name>_<sha1>` pattern.
 */
class DevLoginCustomRecallerGuard extends SessionGuard
{
    /**
     * The cookie name this guard uses for its recaller.
     */
    public function getRecallerName(): string
    {
        return 'keep_me_signed_in';
    }
}
