<?php

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

/**
 * An editor column on a connection this app's config does not define
 */
class MigrateReportingArticle extends Model
{
    protected $connection = 'reporting';
    protected $guarded = [];
    protected $casts = ['body' => AsTiptapContent::class];
}
