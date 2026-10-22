<?php

namespace App\Models;

/**
 * A child of a concrete model, on the same table: scanned once, as the parent
 */
class MigrateChildPost extends MigratePost
{
    protected $table = 'migrate_posts';
}
