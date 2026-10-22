<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

class PurgeBlogNote extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
