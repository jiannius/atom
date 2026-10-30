<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An auth model keyed by a string in `id`, set by hand: not incrementing, and the key type left at Eloquent's default (int).
 */
class DevLoginStringIdMember extends Authenticatable
{
    protected $table = 'string_id_members';

    public $incrementing = false;

    protected $guarded = [];
}
