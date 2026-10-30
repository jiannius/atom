<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * A member model that leaves blocked accounts out of dev login and lists the rest by name.
 */
class DevLoginActiveMember extends DevLoginMember
{
    /**
     * Accounts the local dev login offers and accepts.
     */
    public function scopeDevLogin(Builder $query): void
    {
        $query->whereNull('blocked_at')->reorder('name');
    }
}
