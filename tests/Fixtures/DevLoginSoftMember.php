<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A member model whose deleted accounts are hidden by a global scope.
 */
class DevLoginSoftMember extends DevLoginMember
{
    use SoftDeletes;
}
