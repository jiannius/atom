<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Still names the cast v4 removed, so the cast is a plain string here
 */
class MigrateRemovedCast extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['body' => 'Jiannius\\Atom\\Casts\\AsEditorContent'];
}
