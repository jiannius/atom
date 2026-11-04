<?php

namespace Jiannius\Atom\Services;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Traits\ReportsProgress;
use LogicException;
use ReflectionMethod;
use Throwable;

/**
 * The cache side of job progress (#115). Internal: hosts go through JobProgress, ReportsProgress and WithJobProgress.
 *
 * Where each state is written: `queued` by the payload hook in the request (when the job is really pushed), `running` by the
 * JobProcessing hook, `finished` by the job's finish() or the JobProcessed hook, `failed` by the JobFailed hook. The key is
 * built here, on the server, from the signed-in user, the scope and the job name, and travels to the worker in the queue
 * payload: the worker never asks who is signed in and never calls the scope resolver.
 *
 * The entry holds scalars only (no Carbon, no models), so a cache that restricts unserialize() classes reads it, and it is
 * validated field by field on read: a corrupt value reads as no entry, a page never 500s on it.
 *
 * @internal
 */
final class JobProgressStore
{
    public const PAYLOAD_KEY = 'atom:job-progress';

    public const SCOPE_BINDING = 'atom.job-progress.scope';

    public const VERSION = 1;

    public const MAX_ERRORS = 100;

    public const MAX_ERROR_BYTES = 500;

    public const MAX_FILE_BYTES = 4096;

    public const MAX_FILE_NAME_BYTES = 255;

    private const KEY_PATTERN = '/^atom:job-progress:[0-9a-f]{64}$/';

    private const STATUSES = [JobProgress::QUEUED, JobProgress::RUNNING, JobProgress::FINISHED, JobProgress::FAILED];

    private const GUARDED_METHODS = ['progress', 'error', 'finish'];

    /** @var array<string, true> failures already logged by this process */
    private static array $logged = [];

    /** @var array<class-string, bool> */
    private static array $reports = [];

    /** @var array<class-string, true> */
    private static array $checked = [];

    /** Forget what this process remembers (tests). */
    public static function flushState(): void
    {
        self::$logged = [];
        self::$reports = [];
        self::$checked = [];
    }

    /**
     * The key for the signed-in user's entry for $name in the current scope, or null when no one is signed in.
     * Built from the user's class and id, the scope and the name, hashed: no secret is needed because no endpoint
     * ever accepts a key, and a shared cache or a log line does not show user ids.
     */
    public static function key(string $name): ?string
    {
        if ($name === '') {
            throw new LogicException('A job progress name cannot be empty.');
        }

        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $scope = self::scope();

        return 'atom:job-progress:'.hash('sha256', json_encode([
            $user::class,
            self::text((string) $user->getAuthIdentifier()),
            $scope === null ? null : self::text((string) $scope),
            self::text($name),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * A part of the key that json_encode() cannot fail on: valid UTF-8 as it is (so a key built from ordinary ids and names
     * is the same as ever), anything else, a binary uuid for instance, as its hex in an object. json_encode() returns false on
     * invalid UTF-8, and hashing false is hashing '', which would give every such user and name one key.
     */
    private static function text(string $part): string|array
    {
        return mb_check_encoding($part, 'UTF-8') ? $part : ['hex' => bin2hex($part)];
    }

    /**
     * The tenant the signed-in user acts in, from JobProgress::scopeUsing().
     */
    public static function scope(): int|string|null
    {
        if (! app()->bound(self::SCOPE_BINDING)) {
            throw new LogicException('Job progress needs to know which tenant the signed-in user acts in. Register JobProgress::scopeUsing(fn () => org()?->id) in a service provider; an app without tenants registers fn () => null.');
        }

        $scope = app(self::SCOPE_BINDING)();

        if ($scope !== null && ! is_int($scope) && ! is_string($scope)) {
            throw new LogicException('The JobProgress::scopeUsing() resolver must return an int, a string or null, got '.get_debug_type($scope).'.');
        }

        return $scope;
    }

    /**
     * The signed-in user's latest run of $name in the current scope, read fresh.
     */
    public static function find(string $name): JobProgress
    {
        $repository = self::repository();
        $key = self::key($name);
        $entry = $key === null ? null : self::normalise($repository->get($key));

        return $entry === null ? new JobProgress($name) : self::hydrate($name, $entry);
    }

    /**
     * Forget the signed-in user's entry for $name in the current scope.
     */
    public static function forget(string $name): void
    {
        $repository = self::repository();
        $key = self::key($name);

        if ($key !== null) {
            $repository->forget($key);
        }
    }

    /**
     * Queue::createPayloadUsing(): runs in the request, while the job is still an object and before it is
     * serialised, and only for a job that is really being pushed (after a unique lock, after commit). Writes
     * `queued` and hands the key to the worker in the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> the keys to merge into the payload
     */
    public static function payload(array $payload): array
    {
        $job = $payload['data']['command'] ?? null;

        if (! is_object($job) || ! self::reportsProgress($job)) {
            return [];
        }

        self::assertNotShadowed($job);

        $key = self::key($job->progressName());

        if ($key === null) {
            return [];
        }

        self::write($key, self::entry((string) ($payload['uuid'] ?? ''), JobProgress::QUEUED, [
            'queued_at' => now()->getTimestamp(),
        ]), $job::class);

        return [self::PAYLOAD_KEY => $key];
    }

    /**
     * JobProcessing: a worker picked the job up. Every attempt starts at 0.
     */
    public static function processing(Job $job): void
    {
        [$key, $payload] = self::payloadOf($job);

        if ($key === null) {
            return;
        }

        self::write($key, self::entry((string) ($payload['uuid'] ?? ''), JobProgress::RUNNING, [
            'queued_at' => is_int($payload['createdAt'] ?? null) ? $payload['createdAt'] : null,
            'started_at' => now()->getTimestamp(),
        ]), $job->resolveName());
    }

    /**
     * JobProcessed: a job that never called finish() ends as finished (without a file), unless it was released
     * or failed, or the entry already belongs to a newer run.
     */
    public static function processed(Job $job): void
    {
        [$key, $payload] = self::payloadOf($job);

        if ($key === null || $job->isReleased() || $job->hasFailed()) {
            return;
        }

        self::guarded(function () use ($key, $payload, $job) {
            $entry = self::normalise(self::repository()->get($key));

            if ($entry === null || $entry['run'] !== (string) ($payload['uuid'] ?? '') || ! in_array($entry['status'], [JobProgress::QUEUED, JobProgress::RUNNING], true)) {
                return;
            }

            self::write($key, array_merge($entry, ['status' => JobProgress::FINISHED, 'ended_at' => now()->getTimestamp()]), $job->resolveName());
        }, $job->resolveName());
    }

    /**
     * JobFailed, on every failure path (and maybe in another process than the one that ran the job): keeps the
     * progress and the errors, and stores no exception message. A newer run's entry is left alone.
     */
    public static function failed(Job $job): void
    {
        [$key, $payload] = self::payloadOf($job);

        if ($key === null) {
            return;
        }

        self::guarded(function () use ($key, $payload, $job) {
            $run = (string) ($payload['uuid'] ?? '');
            $entry = self::normalise(self::repository()->get($key));

            if ($entry !== null && $entry['run'] !== $run) {
                return;
            }

            self::write($key, array_merge($entry ?? self::entry($run, JobProgress::FAILED), [
                'status' => JobProgress::FAILED,
                'file' => null,
                'file_name' => null,
                'ended_at' => now()->getTimestamp(),
            ]), $job->resolveName());
        }, $job->resolveName());
    }

    /**
     * What a job writes (progress(), error(), finish()): never fails the job, a broken store is logged once.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function write(string $key, array $entry, string $context = ''): bool
    {
        return self::guarded(function () use ($key, $entry) {
            self::repository()->put($key, self::cap($entry), self::ttl());

            return true;
        }, $context) === true;
    }

    /**
     * A fresh entry.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function entry(string $run, string $status, array $extra = []): array
    {
        return array_merge([
            'v' => self::VERSION,
            'run' => $run,
            'status' => $status,
            'done' => 0,
            'total' => null,
            'errors' => [],
            'error_count' => 0,
            'file' => null,
            'file_name' => null,
            'queued_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'updated_at' => now()->getTimestamp(),
        ], $extra);
    }

    /** The whole percent a determinate bar shows, null for an indeterminate one. */
    public static function percentOf(int $done, ?int $total): ?int
    {
        return $total === null || $total <= 0 ? null : max(0, min(100, intdiv(max(0, $done) * 100, $total)));
    }

    /**
     * The key a running job reports under: the one the payload carries; with no queue job at all (handle() called
     * directly, dispatchNow) the signed-in user and scope own it, which keeps a host's own tests simple.
     */
    public static function keyFor(object $job): ?string
    {
        $queueJob = self::queueJobOf($job);

        if ($queueJob instanceof Job) {
            return self::payloadOf($queueJob)[0];
        }

        return self::key($job->progressName());
    }

    /**
     * The queue job a running job reports through, or null when it is not on a queue. dispatchNow() gives a job a
     * SyncJob with an empty payload (no uuid, no key) so that $this->job works: that is no queue at all, and it is
     * treated like handle() called directly. A real sync dispatch has a payload with a uuid, and its own key or none.
     */
    private static function queueJobOf(object $job): ?Job
    {
        $queueJob = $job->job ?? null;

        if (! $queueJob instanceof Job) {
            return null;
        }

        return $queueJob instanceof SyncJob && $queueJob->payload() === [] ? null : $queueJob;
    }

    /** The run id the payload carries, or a new one for a job with no queue job. */
    public static function runOf(object $job): string
    {
        $queueJob = self::queueJobOf($job);

        if ($queueJob instanceof Job) {
            return (string) (self::payloadOf($queueJob)[1]['uuid'] ?? '');
        }

        return (string) str()->uuid();
    }

    /** @return array<string, mixed> */
    public static function payloadFor(object $job): array
    {
        $queueJob = self::queueJobOf($job);

        return $queueJob instanceof Job ? self::payloadOf($queueJob)[1] : [];
    }

    /**
     * The store, checked. A store that cannot carry a value from the web request to the worker is a configuration
     * error: `null` keeps nothing, and `array` lives in one process (fine for tests and the sync queue, where the job
     * runs inside the request). It throws; the queue side runs it inside guarded(), the page side lets it through.
     */
    private static function repository(): Repository
    {
        $repository = Cache::store(config('atom.job_progress.store'));
        $store = $repository->getStore();

        if ($store instanceof NullStore) {
            throw new LogicException('The cache store used for job progress is the null store, which keeps nothing. Set atom.job_progress.store to a store that web requests and queue workers share (database, redis, file on one server).');
        }

        if ($store instanceof ArrayStore && ! app()->runningUnitTests() && config('queue.connections.'.config('queue.default').'.driver') !== 'sync') {
            throw new LogicException('The cache store used for job progress is the array store, which lives in one process: the web request and the queue worker would never see each other. Set atom.job_progress.store to a shared store (database, redis, file on one server).');
        }

        return $repository;
    }

    /** The key and payload of a queue job that carries progress, else [null, []]. */
    private static function payloadOf(Job $job): array
    {
        try {
            /* Most jobs carry no progress: look for the key in the raw body before decoding it (three hooks run per job). */
            if (! str_contains($job->getRawBody(), self::PAYLOAD_KEY)) {
                return [null, []];
            }

            $payload = $job->payload();
        } catch (Throwable) {
            return [null, []];
        }

        $key = is_array($payload) ? ($payload[self::PAYLOAD_KEY] ?? null) : null;

        return is_string($key) && preg_match(self::KEY_PATTERN, $key) === 1 ? [$key, $payload] : [null, []];
    }

    /** Does this job use ReportsProgress? Cached per class: the payload hook runs for every job. */
    private static function reportsProgress(object $job): bool
    {
        return self::$reports[$job::class] ??= in_array(ReportsProgress::class, class_uses_recursive($job), true);
    }

    /**
     * A class method silently beats a trait method of the same name, so a job that declares its own progress(),
     * error() or finish() would quietly stop reporting. Refused at dispatch, naming the method. progressName() is
     * the one the job is meant to override.
     */
    private static function assertNotShadowed(object $job): void
    {
        if (isset(self::$checked[$job::class])) {
            return;
        }

        $trait = (new \ReflectionClass(ReportsProgress::class))->getFileName();

        foreach (self::GUARDED_METHODS as $method) {
            if ((new ReflectionMethod($job, $method))->getFileName() !== $trait) {
                throw new LogicException($job::class.' declares its own '.$method.'(), which replaces the one from ReportsProgress and stops the job from reporting progress. Rename it.');
            }
        }

        self::$checked[$job::class] = true;
    }

    /**
     * Run a write-side step: a cache exception is logged once per process (the class, where it came from and the
     * exception class, never the value) and never fails the job or the queue machinery.
     */
    private static function guarded(callable $step, string $context): mixed
    {
        try {
            return $step();
        } catch (Throwable $e) {
            $id = md5($context.'|'.$e->getFile().':'.$e->getLine().'|'.$e::class);

            if (! isset(self::$logged[$id])) {
                if (count(self::$logged) >= 100) {
                    self::$logged = [];
                }

                self::$logged[$id] = true;

                Log::warning('Job progress could not be stored.', [
                    'job' => $context,
                    'exception' => $e::class,
                    'at' => basename($e->getFile()).':'.$e->getLine(),
                ]);
            }

            return null;
        }
    }

    private static function ttl(): int
    {
        return max(60, (int) config('atom.job_progress.ttl', 86400));
    }

    /**
     * Bound what is stored: at most 100 messages of 500 bytes (the rest are only counted), valid UTF-8, a short
     * file name. About 55 KB in the worst case.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function cap(array $entry): array
    {
        $errors = array_slice(array_values($entry['errors'] ?? []), 0, self::MAX_ERRORS);
        $entry['errors'] = array_map(fn ($message) => mb_strcut(mb_scrub((string) $message), 0, self::MAX_ERROR_BYTES), $errors);
        $entry['error_count'] = max((int) ($entry['error_count'] ?? 0), count($entry['errors']));
        $entry['file'] = isset($entry['file']) ? mb_strcut(mb_scrub((string) $entry['file']), 0, self::MAX_FILE_BYTES) : null;
        $entry['file_name'] = isset($entry['file_name']) ? mb_strcut(mb_scrub((string) $entry['file_name']), 0, self::MAX_FILE_NAME_BYTES) : null;
        $entry['updated_at'] = now()->getTimestamp();

        return $entry;
    }

    /**
     * Validate a stored value field by field. Anything unexpected (not an array, another version, an unknown
     * status, a wrong type) reads as no entry.
     *
     * @return array<string, mixed>|null
     */
    private static function normalise(mixed $value): ?array
    {
        if (! is_array($value) || ($value['v'] ?? null) !== self::VERSION || ! in_array($value['status'] ?? null, self::STATUSES, true)) {
            return null;
        }

        $int = fn ($v) => is_int($v) && $v >= 0;
        $maybeInt = fn ($v) => $v === null || $int($v);
        $maybeString = fn ($v) => $v === null || is_string($v);

        if (! is_string($value['run'] ?? null)
            || ! $int($value['done'] ?? null)
            || ! (($value['total'] ?? null) === null || (is_int($value['total']) && $value['total'] > 0))
            || ! is_array($value['errors'] ?? null) || ! array_is_list($value['errors'])
            || ! $int($value['error_count'] ?? null)
            || ! $maybeString($value['file'] ?? null) || ! $maybeString($value['file_name'] ?? null)
            || ! $maybeInt($value['queued_at'] ?? null) || ! $maybeInt($value['started_at'] ?? null)
            || ! $maybeInt($value['ended_at'] ?? null) || ! $maybeInt($value['updated_at'] ?? null)) {
            return null;
        }

        foreach ($value['errors'] as $error) {
            if (! is_string($error)) {
                return null;
            }
        }

        $value['errors'] = array_map(fn ($message) => mb_strcut($message, 0, self::MAX_ERROR_BYTES), array_slice($value['errors'], 0, self::MAX_ERRORS));
        $value['error_count'] = max($value['error_count'], count($value['errors']));

        return $value;
    }

    /** @param  array<string, mixed>  $entry */
    private static function hydrate(string $name, array $entry): JobProgress
    {
        return new JobProgress(
            name: $name,
            status: $entry['status'],
            done: $entry['done'],
            total: $entry['total'],
            errors: $entry['errors'],
            errorCount: $entry['error_count'],
            file: $entry['file'],
            fileName: $entry['file_name'],
            run: $entry['run'],
            queuedAt: $entry['queued_at'],
            startedAt: $entry['started_at'],
            endedAt: $entry['ended_at'],
            updatedAt: $entry['updated_at'],
        );
    }
}
