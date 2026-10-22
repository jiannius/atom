<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * Soft deletes and a global scope that hides unpublished rows: neither hides a row from the command
 */
class MigrateScopedPost extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];

    protected static function booted(): void
    {
        static::addGlobalScope('published', fn (Builder $query) => $query->where('published', 1));
    }
}
