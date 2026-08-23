<?php

namespace App\Actions;

use Jiannius\Atom\Contracts\WebAction;

/**
 * An action that has opted in to HTTP invocation, with a second public method
 * that must stay unreachable from the endpoint.
 */
class Open implements WebAction
{
    /**
     * Handle the action
     */
    public function handle($params) : array
    {
        return ['ran' => 'handle', 'params' => $params];
    }

    /**
     * A public helper that is not the action's entrypoint
     */
    public function secret($params) : array
    {
        return ['ran' => 'secret'];
    }
}
