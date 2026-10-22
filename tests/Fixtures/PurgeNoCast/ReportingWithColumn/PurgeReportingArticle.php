<?php

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * An editor column on a connection this app's config does not define: its rows cannot be read
 */
class PurgeReportingArticle extends Model
{
    protected $connection = 'reporting';
    protected $guarded = [];
    protected $casts = ['doc' => AsTiptapContent::class];
}
