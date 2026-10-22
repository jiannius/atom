<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * A string primary key
 */
class MigrateUuidPost extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
