<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;

/** A job that dispatches a job that reports progress, the way $mode says. */
class JobProgressDispatchesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $mode = 'dispatch') {}

    public function handle(): void
    {
        match ($this->mode) {
            'dispatch' => JobProgressFixtureJob::dispatch(),
            'dispatchSync' => JobProgressFixtureJob::dispatchSync(),
            'dispatchNow' => Bus::dispatchNow(new JobProgressFixtureJob),
            'direct' => (new JobProgressFixtureJob)->handle(),
            'chain' => Bus::chain([new JobProgressFixtureJob])->dispatch(),
        };
    }
}
