<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Jiannius\Atom\Traits\ReportsProgress;

/** A unique job: a second dispatch while the lock is held is refused before anything is pushed. */
class JobProgressUniqueJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable, ReportsProgress;

    public function uniqueId(): string
    {
        return 'fixture';
    }

    public function handle(): void
    {
        $this->finish();
    }
}
