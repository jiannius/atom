<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * A file declaring an abstract base first and the concrete model after it: the
 * purge must find the model, not stop at the first declaration.
 */
abstract class PurgeBaseThing extends Model
{
    public $timestamps = false;
    protected $guarded = [];
}

class PurgeMultiThing extends PurgeBaseThing
{
    protected $casts = ['doc' => AsTiptapContent::class];
}
