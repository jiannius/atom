<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An auth model keyed by a string uuid in a column that is not called `id`.
 */
class DevLoginUuidMember extends Authenticatable
{
    protected $table = 'uuid_members';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
