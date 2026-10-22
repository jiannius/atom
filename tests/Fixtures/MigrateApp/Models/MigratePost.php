<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

class MigratePost extends Model
{
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
