<?php

namespace App\Actions;

use Jiannius\Atom\Contracts\WebAction;

/**
 * An action that is HTTP-reachable but decides for itself who may call it.
 */
class Guarded implements WebAction
{
    /**
     * Authorise the caller
     */
    public function authorize($params) : bool
    {
        return data_get($params, 'pass') === 'yes';
    }

    /**
     * Handle the action
     */
    public function handle($params) : array
    {
        return ['ran' => true];
    }
}
