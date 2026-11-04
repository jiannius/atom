<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Jiannius\Atom\Traits\ReportsProgress;

/** A job that also lists InteractsWithQueue itself, as most do: PHP must accept both. */
class JobProgressBothTraitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsProgress;

    public function handle(): void
    {
        $this->finish();
    }
}
