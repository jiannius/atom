<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsEditorContent;

/**
 * Still on AsEditorContent: valid on 3.x, so the migration converts its rows
 * and reports the cast as not yet switched
 */
class MigrateLegacyCast extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => AsEditorContent::class];
}
