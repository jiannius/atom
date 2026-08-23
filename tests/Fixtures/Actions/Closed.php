<?php

namespace App\Actions;

/**
 * An action that has NOT opted in to HTTP invocation. Stands in for the bulk of
 * a host app's App\Actions\* classes — server-side helpers that were never meant
 * to be reachable from the browser.
 */
class Closed
{
    /**
     * Handle the action
     */
    public function handle($params) : array
    {
        return ['ran' => true];
    }
}
