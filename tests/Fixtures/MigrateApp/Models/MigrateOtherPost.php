<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * A model on a connection that is not the default one
 */
class MigrateOtherPost extends Model
{
    protected $connection = 'other';
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
