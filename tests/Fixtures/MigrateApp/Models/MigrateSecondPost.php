<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * A second model with an editor column, so the same row id can be in two places
 */
class MigrateSecondPost extends Model
{
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
