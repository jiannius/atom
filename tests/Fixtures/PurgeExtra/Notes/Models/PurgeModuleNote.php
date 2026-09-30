<?php

namespace Modules\Notes\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

class PurgeModuleNote extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['doc' => AsTiptapContent::class];
}
