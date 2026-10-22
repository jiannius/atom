<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsEditorContent;
use Jiannius\Atom\Casts\AsTiptapContent;

class Article extends Model
{
    protected $casts = [
        'body' => AsEditorContent::class,
        'summary' => AsTiptapContent::class,
    ];
}
