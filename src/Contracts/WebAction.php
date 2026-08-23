<?php

namespace Jiannius\Atom\Contracts;

/**
 * Marks an action class as reachable from the public POST /atom/action/{name}
 * endpoint. Actions without this contract can still be invoked server-side via
 * atom()->action() / $this->action(), but the endpoint answers 404 for them —
 * the same answer it gives for an action that does not exist, so the endpoint
 * cannot be used to enumerate an app's action classes.
 *
 * handle() is the only method the endpoint will call; the caller cannot choose
 * another one.
 *
 * To gate who may call it, declare authorize() on the action — the endpoint
 * looks for the method and answers 403 when it returns false:
 *
 *     public function authorize($params) : bool
 *     {
 *         return auth()->check();
 *     }
 */
interface WebAction
{
    /**
     * Handle the action
     */
    public function handle($params);
}
