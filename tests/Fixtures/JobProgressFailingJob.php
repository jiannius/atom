<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RuntimeException;

/** A plain job that throws on its only try, so the worker raises JobExceptionOccurred, JobFailed and JobAttempted for it. */
class JobProgressFailingJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('no');
    }
}
