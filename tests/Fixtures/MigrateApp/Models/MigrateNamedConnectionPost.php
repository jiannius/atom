<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * Names the default connection, which is the connection a model that names none uses
 */
class MigrateNamedConnectionPost extends Model
{
    protected $connection = 'testing';
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
