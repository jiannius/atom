<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

class PurgeTiptapArticle extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['doc' => AsTiptapContent::class];
}
