<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

/** A job that signs a user in and leaves them there (audit logging, an auth-based scope): the worker never resets the guard. */
class JobProgressSignsInJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        Auth::setUser(new GenericUser(['id' => 7]));
    }
}
