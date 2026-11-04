<?php

namespace Jiannius\Atom\Traits;

use Illuminate\Queue\InteractsWithQueue;
use Jiannius\Atom\Services\JobProgress;
use Jiannius\Atom\Services\JobProgressStore;

/**
 * Lets a queued job say how far it has got, for <atom:progress :job> to show (issue #115). The queue hooks atom registers
 * write `queued`, `running` and `failed` (and `finished` when the job never says so); the job adds the numbers:
 *
 *   $this->progress(0, $total);
 *   $this->error('Invoice #12: no line items');   // shown on the page, escaped, does not fail the job
 *   $this->finish($path, 'journal.xlsx');           // your file: atom never serves it
 *
 * Public, not protected: a job is never browser-reachable, and public lets it hand `$this` to a service it delegates to.
 * The key is the one the payload carries (built in the request from the signed-in user, the scope and progressName()); a job
 * pushed with nobody signed in is not tracked and every call is a silent no-op. A write that fails (the cache is down) is
 * logged once and never fails the job. A job that declares its own progress(), error() or finish() is refused at dispatch:
 * a class method silently beats a trait method of the same name.
 */
trait ReportsProgress
{
    use InteractsWithQueue;

    /** @var array<string, mixed>|null the entry as this run built it; null when the job is serialised at dispatch */
    protected ?array $jobProgressState = null;

    protected ?string $jobProgressKey = null;

    protected bool $jobProgressKeyResolved = false;

    protected int $jobProgressWrittenAt = 0;

    protected ?int $jobProgressWrittenPercent = null;

    /**
     * The name a page reads this job's progress by: the class, unless overridden (one entry per document type, say).
     * Read at push, in the request.
     */
    public function progressName(): string
    {
        return static::class;
    }

    /**
     * Report how far the job is: $done of $total, or an indeterminate bar with no total. Writes at most once a second,
     * or when the whole percent changes.
     */
    public function progress(int $done = 0, ?int $total = null): void
    {
        if (! $this->startJobProgress()) {
            return;
        }

        $total = $total !== null && $total > 0 ? $total : null;
        $percent = JobProgressStore::percentOf($done, $total);

        $this->jobProgressState['done'] = max(0, $done);
        $this->jobProgressState['total'] = $total;

        $this->flushJobProgress($percent !== $this->jobProgressWrittenPercent);
    }

    /**
     * Add a message to the list the page shows: plain text, escaped on output, the first 100 kept and the rest counted.
     * Does not fail the job. Written at once while under the cap (a job that reports a reason and then throws must not
     * lose it), after that no more than once a second.
     */
    public function error(string $message): void
    {
        if (! $this->startJobProgress()) {
            return;
        }

        $state = &$this->jobProgressState;
        $state['error_count']++;

        $kept = count($state['errors']) < JobProgressStore::MAX_ERRORS;

        if ($kept) {
            $state['errors'][] = $message;
        }

        $this->flushJobProgress($kept);
    }

    /**
     * Mark the job finished. $file is yours (what your download action serves, atom never prints it); $name is what the
     * page shows (basename($file) by default).
     */
    public function finish(?string $file = null, ?string $name = null): void
    {
        if (! $this->startJobProgress()) {
            return;
        }

        $this->jobProgressState['status'] = JobProgress::FINISHED;
        $this->jobProgressState['file'] = filled($file) ? $file : null;
        $this->jobProgressState['file_name'] = filled($name) ? $name : (filled($file) ? basename($file) : null);
        $this->jobProgressState['ended_at'] = now()->getTimestamp();

        $this->flushJobProgress(true);
    }

    /** Resolve the key once and build this run's entry; false when the job is not tracked, or already finished. */
    private function startJobProgress(): bool
    {
        if (! $this->jobProgressKeyResolved) {
            $this->jobProgressKey = JobProgressStore::keyFor($this);
            $this->jobProgressKeyResolved = true;
        }

        if ($this->jobProgressKey === null) {
            return false;
        }

        $this->jobProgressState ??= JobProgressStore::entry(JobProgressStore::runOf($this), JobProgress::RUNNING, [
            'queued_at' => JobProgressStore::payloadFor($this)['createdAt'] ?? now()->getTimestamp(),
            'started_at' => now()->getTimestamp(),
        ]);

        return $this->jobProgressState['status'] === JobProgress::RUNNING;
    }

    /** Write the entry now when $force, or when a second has passed since the last write. */
    private function flushJobProgress(bool $force): void
    {
        $now = now()->getTimestamp();

        if (! $force && $this->jobProgressWrittenAt !== 0 && $now - $this->jobProgressWrittenAt < 1) {
            return;
        }

        JobProgressStore::write($this->jobProgressKey, $this->jobProgressState, static::class);

        $this->jobProgressWrittenAt = $now;
        $this->jobProgressWrittenPercent = JobProgressStore::percentOf($this->jobProgressState['done'], $this->jobProgressState['total']);
    }
}
