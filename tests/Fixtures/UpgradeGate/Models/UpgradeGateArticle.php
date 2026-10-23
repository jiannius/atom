<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Still names the cast v4 removed (as a string, since the class is gone), so atom:upgrade must refuse to write
 */
class UpgradeGateArticle extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['body' => 'Jiannius\\Atom\\Casts\\AsEditorContent'];
}
