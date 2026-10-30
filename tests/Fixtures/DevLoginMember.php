<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An auth model on its own table, so dev login is proven not to assume App\Models\User or a `users` table.
 */
class DevLoginMember extends Authenticatable
{
    protected $table = 'members';

    protected $guarded = [];
}
