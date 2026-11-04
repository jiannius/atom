<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Jiannius\Atom\Traits\ReportsProgress;

/** A job that picks its own progress name, so one class keeps one entry per document type. */
class JobProgressRenamedJob implements ShouldQueue
{
    use Dispatchable, Queueable, ReportsProgress;

    public function __construct(public string $type = 'invoice') {}

    public function progressName(): string
    {
        return 'export:'.$this->type;
    }

    public function handle(): void
    {
        $this->progress(1, 2);
        $this->finish();
    }
}
