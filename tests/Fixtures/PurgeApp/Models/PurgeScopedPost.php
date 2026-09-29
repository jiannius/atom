<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jiannius\Atom\Casts\AsEditorContent;

class PurgeScopedPost extends Model
{
    use SoftDeletes;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => AsEditorContent::class];

    protected static function booted(): void
    {
        static::addGlobalScope('published', fn (Builder $query) => $query->where('published', 1));
    }
}
