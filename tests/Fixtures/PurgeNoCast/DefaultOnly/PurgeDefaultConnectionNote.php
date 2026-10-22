<?php

namespace Modules\Archive\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Names the default connection explicitly: it is the same connection as a model that names none
 */
class PurgeDefaultConnectionNote extends Model
{
    protected $connection = 'testing';
    protected $guarded = [];
}
