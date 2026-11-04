<?php

namespace Jiannius\Atom\Traits;

use Jiannius\Atom\Services\JobProgress;

/**
 * Reads a queued job's progress for the signed-in user, for <atom:progress :job> (issue #115).
 *
 * Two helpers, both PROTECTED, and no properties or hooks: Livewire exposes every public method a component declares to the
 * browser ($wire.<name>()) and sends its return value back, trait methods included, so a public jobProgress() would hand the
 * result file's server path to any client that calls it, and a public forgetJobProgress() would let any client wipe entries.
 * The component's view is evaluated inside a closure bound to the component, so $this->jobProgress() works there although it
 * is protected.
 *
 * Read it where it is shown, in the view or render(), never keep it in a public property: the object is not Wireable (Livewire
 * refuses it, rightly), and an array copy would be client-writable and carry the path to the browser. Outside Livewire call
 * JobProgress::for() directly.
 */
trait WithJobProgress
{
    /**
     * The signed-in user's latest run of the job in the current scope, read fresh. Pass a job class, or the name its
     * progressName() answers.
     */
    protected function jobProgress(string $name): JobProgress
    {
        return JobProgress::for($name);
    }

    /**
     * Forget it: after a download, on dismiss, before offering a new run. The result file is yours to delete.
     */
    protected function forgetJobProgress(string $name): void
    {
        JobProgress::forget($name);
    }
}
