<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Jiannius\Atom\Traits\ReportsProgress;

/** A job whose own error() silently beats the trait's: refused at dispatch. */
class JobProgressShadowingJob implements ShouldQueue
{
    use Dispatchable, Queueable, ReportsProgress;

    public function error(string $message): void {}

    public function handle(): void {}
}
