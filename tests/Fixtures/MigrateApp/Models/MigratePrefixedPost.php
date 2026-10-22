<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * On a connection with a table prefix
 */
class MigratePrefixedPost extends Model
{
    protected $connection = 'prefixed';
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
