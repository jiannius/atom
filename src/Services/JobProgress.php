<?php

namespace Jiannius\Atom\Services;

use Closure;

/**
 * How far a queued job has got, as a page reads it (issue #115). A job reports through
 * Traits\ReportsProgress and a Livewire page reads it back through Traits\WithJobProgress or
 * JobProgress::for(); the entry is one cache value per (signed-in user, scope, job name),
 * and its key is built on the server only, so nothing key-related ever reaches the browser.
 *
 * `$file` is the host's own path and is never printed by atom: the host's download action
 * reads it on the server and re-checks access.
 */
final class JobProgress
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const FINISHED = 'finished';
    public const FAILED = 'failed';

    /**
     * @param  list<string>  $errors  plain text, the first 100 kept
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $status = null,
        public readonly int $done = 0,
        public readonly ?int $total = null,
        public readonly array $errors = [],
        public readonly int $errorCount = 0,
        public readonly ?string $file = null,
        public readonly ?string $fileName = null,
        public readonly ?string $run = null,
        public readonly ?int $queuedAt = null,
        public readonly ?int $startedAt = null,
        public readonly ?int $endedAt = null,
        public readonly ?int $updatedAt = null,
    ) {}

    /**
     * Register the tenant the signed-in user acts in (an org, team or workspace id): part of every key, so a user
     * working in two orgs sees two sets of entries. Return null for an app with no tenants. Required: reading or
     * writing an entry throws until this is registered. The closure lives in the container, not in config
     * (config:cache cannot hold one) and not in a static (it cannot leak between tests).
     */
    public static function scopeUsing(Closure $resolver): void
    {
        app()->instance(JobProgressStore::SCOPE_BINDING, $resolver);
    }

    /**
     * The signed-in user's latest run of $name in the current scope, read fresh. Never null: exists() is false when
     * there is no entry or no one is signed in.
     */
    public static function for(string $name): self
    {
        return JobProgressStore::find($name);
    }

    /**
     * Forget the signed-in user's entry for $name in the current scope. The result file is untouched.
     */
    public static function forget(string $name): void
    {
        JobProgressStore::forget($name);
    }

    /** Is there an entry at all? */
    public function exists(): bool
    {
        return $this->status !== null;
    }

    /** Waiting on a worker. */
    public function isQueued(): bool
    {
        return $this->status === self::QUEUED;
    }

    /** A worker is on it. */
    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    /** Queued or running: the page should keep polling. */
    public function isActive(): bool
    {
        return $this->isQueued() || $this->isRunning();
    }

    /** Done (with or without a file, with or without errors). */
    public function isFinished(): bool
    {
        return $this->status === self::FINISHED;
    }

    /** The job stopped before it finished. */
    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /** Did the job report any message through error()? */
    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /** 0 to 100 when the total is known (or the job is finished); null for an indeterminate bar. */
    public function percent(): ?int
    {
        if ($this->isFinished()) {
            return 100;
        }

        if ($this->total === null || $this->total <= 0) {
            return null;
        }

        return max(0, min(100, intdiv(max(0, $this->done) * 100, $this->total)));
    }

    /** How many messages were reported beyond the ones kept. */
    public function moreErrors(): int
    {
        return max(0, $this->errorCount - count($this->errors));
    }
}
