<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * Two editor columns on one row
 */
class MigrateTwoColumnPost extends Model
{
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class, 'summary' => AsTiptapContent::class];
}
