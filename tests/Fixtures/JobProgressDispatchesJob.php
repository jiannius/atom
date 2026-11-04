<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;

/** A job that dispatches a job that reports progress, the way $mode says. */
class JobProgressDispatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $mode = 'dispatch') {}

    public function handle(): void
    {
        if ($this->mode === 'failThenDispatch') {
            // fail() fires JobFailed in the middle of handle(), and the job goes on
            $this->fail();
            JobProgressFixtureJob::dispatch();

            return;
        }

        match ($this->mode) {
            'dispatch' => JobProgressFixtureJob::dispatch(),
            'dispatchSync' => JobProgressFixtureJob::dispatchSync(),
            'dispatchNow' => Bus::dispatchNow(new JobProgressFixtureJob),
            'direct' => (new JobProgressFixtureJob)->handle(),
            'chain' => Bus::chain([new JobProgressFixtureJob])->dispatch(),
        };
    }
}
