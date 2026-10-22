<?php

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No editor column, on a connection this app's config does not define
 */
class PurgeReportingNote extends Model
{
    protected $connection = 'reporting';
    protected $guarded = [];
}
