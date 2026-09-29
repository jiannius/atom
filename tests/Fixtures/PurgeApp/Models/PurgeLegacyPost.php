<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsEditorContent;

class PurgeLegacyPost extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => AsEditorContent::class];
}
