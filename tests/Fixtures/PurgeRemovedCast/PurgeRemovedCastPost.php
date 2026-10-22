<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that still names the cast v4 removed. The class is gone, so the cast is
 * a plain string here: the purge command must abort on it, not skip the column.
 */
class PurgeRemovedCastPost extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => 'Jiannius\\Atom\\Casts\\AsEditorContent'];
}
