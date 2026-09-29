<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsEditorContent;

class PurgeBlogNote extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => AsEditorContent::class];
}
