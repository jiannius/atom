<?php

namespace Modules\Archive\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No editor cast at all (a model whose cast was changed, say), on a connection of its own
 */
class PurgeOtherConnectionNote extends Model
{
    protected $connection = 'other';
    protected $guarded = [];
}
