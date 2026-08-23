<?php

namespace App\Actions;

/**
 * A host-app action that opts in by extending an already-opted-in one — the
 * documented upgrade path for apps overriding Jiannius\Atom\Actions\GetOptions.
 */
class Inherited extends Open
{
    /**
     * Handle the action
     */
    public function handle($params) : array
    {
        return ['ran' => 'inherited'];
    }
}
