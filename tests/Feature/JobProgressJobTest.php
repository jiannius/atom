<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Services\JobProgress;
use Jiannius\Atom\Services\JobProgressStore;
use Jiannius\Atom\Tests\Fixtures\JobProgressBothTraitsJob;
use Jiannius\Atom\Tests\Fixtures\JobProgressFixtureJob;
use Jiannius\Atom\Tests\Fixtures\JobProgressRenamedJob;
use Jiannius\Atom\Tests\Fixtures\JobProgressShadowingJob;
use Jiannius\Atom\Tests\Fixtures\JobProgressUniqueJob;

// A job reports into one cache entry that a page reads back for the signed-in user and scope only (#115). These tests use a REAL
// database cache and a REAL database queue, and an in-process worker, because that is the path that matters: the request that pushes
// the job and the worker that runs it share nothing but the cache table and the queue payload.

beforeEach(function () {
    config([
        'cache.default' => 'database',
        'queue.default' => 'database',
        'queue.failed.database' => 'testing',
    ]);

    Schema::create('cache', function ($table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration')->index();
    });

    Schema::create('cache_locks', function ($table) {
        $table->string('key')->primary();
        $table->string('owner');
        $table->integer('expiration')->index();
    });

    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    Schema::create('failed_jobs', function ($table) {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });

    JobProgress::scopeUsing(fn () => 'org-1');
});

/** Sign in as the user with this id; the scope is whatever scopeUsing() currently answers. */
function jpSignIn(int|string $id = 7): Authenticatable
{
    $user = new GenericUser(['id' => $id]);

    Auth::setUser($user);

    return $user;
}

/** Run the next queued job in this process, as a worker would: nobody is signed in there, and it never asks the scope. */
function jpWork(): void
{
    Auth::forgetUser();
    JobProgress::scopeUsing(fn () => throw new RuntimeException('the worker must not ask the scope'));

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);

    JobProgress::scopeUsing(fn () => 'org-1');
}

function jpEntryRows(): array
{
    return DB::table('cache')->where('key', 'like', '%atom:job-progress:%')->get()->all();
}

/** The cache's raw bytes for the entry of the job, whoever is signed in: for the "no object, no message" assertions. */
function jpRaw(): string
{
    return implode("\n", array_map(fn ($row) => $row->value, jpEntryRows()));
}

it('runs across a real queue and worker, and shows the entry to its own user and scope only', function () {
    jpSignIn(7);

    JobProgressFixtureJob::dispatch();

    $queued = JobProgress::for(JobProgressFixtureJob::class);
    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($queued->isQueued())->toBeTrue()
        ->and($queued->run)->toBe($payload['uuid'])
        ->and($payload)->toHaveKey('atom:job-progress');

    jpWork();
    jpSignIn(7);

    $done = JobProgress::for(JobProgressFixtureJob::class);

    expect($done->isFinished())->toBeTrue()
        ->and($done->run)->toBe($payload['uuid'])
        ->and($done->done)->toBe(2)
        ->and($done->total)->toBe(4)
        ->and($done->errors)->toBe(['row 3'])
        ->and($done->file)->toBe('/data/exports/journal-7.xlsx')
        ->and($done->fileName)->toBe('journal.xlsx')
        ->and($done->percent())->toBe(100);

    // another user, and the same user in another org, see nothing
    jpSignIn(8);
    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();

    jpSignIn(7);
    JobProgress::scopeUsing(fn () => 'org-2');
    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();

    Auth::forgetUser();
    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();
});

it('keeps one row per entry, holding scalars only: no object, no exception message', function () {
    jpSignIn(7);

    JobProgressFixtureJob::dispatch('throws');
    jpWork();

    $rows = jpEntryRows();

    expect($rows)->toHaveCount(1)
        ->and(preg_match('/(^|[;{])O:\d+:"/', $rows[0]->value))->toBe(0)
        ->and(unserialize($rows[0]->value, ['allowed_classes' => false]))->toBeArray()
        ->and($rows[0]->value)->not->toContain('secret');
});

it('builds the key from the user, the scope and the name, and from nothing the browser sends', function (callable $change) {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch();

    $change();

    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();
})->with([
    'another user id' => [fn () => jpSignIn(8)],
    'another user class' => [fn () => Auth::setUser(new class(['id' => 7]) extends GenericUser {})],
    'another scope' => [fn () => JobProgress::scopeUsing(fn () => 'org-2')],
    'no scope' => [fn () => JobProgress::scopeUsing(fn () => null)],
]);

it('treats a numeric and a string user id as one user', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch();

    jpSignIn('7');

    expect(JobProgress::for(JobProgressFixtureJob::class)->isQueued())->toBeTrue();
});

it('writes queued when the job is pushed, not when it is built', function () {
    jpSignIn(7);

    $job = new JobProgressFixtureJob;

    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();

    dispatch($job);

    expect(JobProgress::for(JobProgressFixtureJob::class)->isQueued())->toBeTrue();
});

it('lets a unique job refused by its lock leave the running entry alone', function () {
    jpSignIn(7);

    JobProgressUniqueJob::dispatch();
    $first = JobProgress::for(JobProgressUniqueJob::class);

    $this->travel(5)->seconds();

    JobProgressUniqueJob::dispatch();

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(JobProgress::for(JobProgressUniqueJob::class)->run)->toBe($first->run)
        ->and(JobProgress::for(JobProgressUniqueJob::class)->updatedAt)->toBe($first->updatedAt);
});

it('ends as failed, with the progress and errors it had and no exception message, when the job throws', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch('throws');
    jpWork();
    jpSignIn(7);

    $entry = JobProgress::for(JobProgressFixtureJob::class);

    expect($entry->isFailed())->toBeTrue()
        ->and($entry->done)->toBe(2)
        ->and($entry->total)->toBe(4)
        ->and($entry->errors)->toBe(['row 3: no line items'])
        ->and(jpRaw())->not->toContain('SQLSTATE')->not->toContain('secret-host')->not->toContain('/srv/app');
});

it('ends as failed when the job fails itself', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch('fails');
    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->isFailed()->toBeTrue()
        ->done->toBe(2);
});

it('ends as failed when the job has already used up its attempts and never runs', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch();

    DB::table('jobs')->update(['attempts' => 1]);   // tries is 1: the worker fails it instead of running it

    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->isFailed()->toBeTrue()
        ->file->toBeNull();
});

it('stays running, and is not called finished, when the job releases itself', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch('releases');
    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->isRunning()->toBeTrue()
        ->done->toBe(1);
});

it('ends as finished, without a file, when the job never calls finish()', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch('forgets');
    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->isFinished()->toBeTrue()
        ->file->toBeNull()
        ->percent()->toBe(100);
});

it('leaves a newer run alone: neither JobProcessed nor JobFailed of an older run overwrites it', function (string $mode) {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch($mode);
    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->run->toBe('a-newer-run')
        ->isRunning()->toBeTrue();
})->with(['superseded', 'supersededThrows']);

it('tracks nothing for a job pushed with nobody signed in, and every call is a silent no-op', function () {
    Log::spy();

    JobProgressFixtureJob::dispatch();

    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($payload)->not->toHaveKey('atom:job-progress')
        ->and(jpEntryRows())->toBeEmpty();

    jpWork();

    expect(jpEntryRows())->toBeEmpty();
    Log::shouldNotHaveReceived('warning');
});

it('lets a job that is not on a queue, handle() called directly, report under the signed-in user and scope', function () {
    jpSignIn(7);

    (new JobProgressFixtureJob)->handle();

    expect(JobProgress::for(JobProgressFixtureJob::class))
        ->isFinished()->toBeTrue()
        ->fileName->toBe('journal.xlsx');
});

it('goes through the payload hook for dispatchSync, so the run id is the queue payload uuid', function () {
    jpSignIn(7);

    JobProgressFixtureJob::dispatchSync();

    $entry = JobProgress::for(JobProgressFixtureJob::class);

    expect($entry->isFinished())->toBeTrue()
        ->and($entry->run)->toMatch('/^[0-9a-f-]{36}$/');
});

it('accepts a job that lists InteractsWithQueue itself next to ReportsProgress', function () {
    jpSignIn(7);

    JobProgressBothTraitsJob::dispatch();
    jpWork();
    jpSignIn(7);

    expect(JobProgress::for(JobProgressBothTraitsJob::class)->isFinished())->toBeTrue();
});

it('writes at most once a second, or when the whole percent changes', function () {
    jpSignIn(7);
    $this->freezeTime();

    $writes = 0;
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
        if (str_contains($event->key, 'atom:job-progress:')) {
            $writes++;
        }
    });

    (new JobProgressFixtureJob('many'))->handle();

    // 1 for the first call, 100 for the whole percents 1..100 (the first call is already 0%), 1 for finish() of the
    // trailing call? 'many' does not finish: the bound is what matters
    expect($writes)->toBeLessThanOrEqual(102)->toBeGreaterThan(90);
});

it('writes an indeterminate bar at most once a second', function () {
    jpSignIn(7);
    $this->freezeTime();

    $writes = 0;
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
        $writes += str_contains($event->key, 'atom:job-progress:') ? 1 : 0;
    });

    $job = new JobProgressFixtureJob('forgets');
    $job->progress();

    foreach (range(1, 50) as $i) {
        $job->progress($i);
    }

    expect($writes)->toBe(1);

    $this->travel(2)->seconds();
    $job->progress(99);

    expect($writes)->toBe(2)
        ->and(JobProgress::for(JobProgressFixtureJob::class)->done)->toBe(99);
});

it('keeps the first 100 messages, counts them all, and flushes the lot on finish()', function () {
    jpSignIn(7);
    $this->freezeTime();

    (new JobProgressFixtureJob('errors'))->handle();

    $entry = JobProgress::for(JobProgressFixtureJob::class);

    expect($entry->errors)->toHaveCount(100)
        ->and($entry->errors[99])->toBe('row 100')
        ->and($entry->errorCount)->toBe(150)
        ->and($entry->moreErrors())->toBe(50);
});

it('writes a message at once, so a job that reports a reason and then throws does not lose it', function () {
    jpSignIn(7);
    $this->freezeTime();

    $job = new JobProgressFixtureJob('forgets');
    $job->progress(1, 4);
    $job->error('first');
    $job->error('second');

    expect(JobProgress::for(JobProgressFixtureJob::class)->errors)->toBe(['first', 'second']);
});

it('cuts a long message to 500 bytes and scrubs invalid UTF-8', function () {
    jpSignIn(7);

    $job = new JobProgressFixtureJob('forgets');
    $job->error("bad \xFF byte ".str_repeat('é', 400));
    $job->finish(null, str_repeat('n', 400));

    $entry = JobProgress::for(JobProgressFixtureJob::class);

    expect(strlen($entry->errors[0]))->toBeLessThanOrEqual(500)
        ->and(mb_check_encoding($entry->errors[0], 'UTF-8'))->toBeTrue()
        ->and(strlen($entry->fileName))->toBe(255);
});

/** A valid stored entry with one field changed. */
function jpStored(array $change = []): array
{
    return array_merge([
        'v' => 1, 'run' => 'r', 'status' => 'running', 'done' => 1, 'total' => 4, 'errors' => ['a'], 'error_count' => 1,
        'file' => null, 'file_name' => null, 'queued_at' => 1, 'started_at' => 1, 'ended_at' => null, 'updated_at' => 1,
    ], $change);
}

it('reads a corrupt or foreign value as no entry, never as an error', function (mixed $value) {
    jpSignIn(7);

    Cache::put(JobProgressStore::key(JobProgressFixtureJob::class), $value, 600);

    expect(fn () => JobProgress::for(JobProgressFixtureJob::class))->not->toThrow(Throwable::class)
        ->and(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();
})->with([
    'a string' => ['garbage'],
    'an int' => [42],
    'another version' => [fn () => jpStored(['v' => 2])],
    'no version' => [fn () => array_diff_key(jpStored(), ['v' => 1])],
    'an unknown status' => [fn () => jpStored(['status' => 'stopped'])],
    'errors that are not a list' => [fn () => jpStored(['errors' => ['x' => 'a']])],
    'errors that are not text' => [fn () => jpStored(['errors' => [['nested']]])],
    'a negative count' => [fn () => jpStored(['done' => -1])],
    'a zero total' => [fn () => jpStored(['total' => 0])],
    'a file that is an array' => [fn () => jpStored(['file' => ['x']])],
    'a run that is an int' => [fn () => jpStored(['run' => 5])],
]);

it('re-caps a stored list on read', function () {
    jpSignIn(7);

    Cache::put(JobProgressStore::key(JobProgressFixtureJob::class), jpStored([
        'errors' => array_map(fn ($i) => str_repeat('x', 900).$i, range(1, 300)),
        'error_count' => 300,
    ]), 600);

    $entry = JobProgress::for(JobProgressFixtureJob::class);

    expect($entry->errors)->toHaveCount(100)
        ->and(strlen($entry->errors[0]))->toBe(500)
        ->and($entry->errorCount)->toBe(300);
});

it('forgets the entry of the signed-in user only', function () {
    jpSignIn(7);
    JobProgressFixtureJob::dispatch();

    jpSignIn(8);
    JobProgressFixtureJob::dispatch();

    JobProgress::forget(JobProgressFixtureJob::class);

    expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();

    jpSignIn(7);
    expect(JobProgress::for(JobProgressFixtureJob::class)->isQueued())->toBeTrue();
});

describe('the cache store', function () {
    it('refuses a null store on a page read and on forget', function () {
        config(['cache.stores.nowhere' => ['driver' => 'null'], 'atom.job_progress.store' => 'nowhere']);
        jpSignIn(7);

        expect(fn () => JobProgress::for(JobProgressFixtureJob::class))->toThrow(LogicException::class, 'null store')
            ->and(fn () => JobProgress::forget(JobProgressFixtureJob::class))->toThrow(LogicException::class, 'null store');
    });

    it('refuses the array store when the queue is not sync and this is not a test run', function () {
        config(['atom.job_progress.store' => 'array']);
        $this->app['env'] = 'local';
        jpSignIn(7);

        expect(fn () => JobProgress::for(JobProgressFixtureJob::class))->toThrow(LogicException::class, 'array store');
    });

    it('accepts the array store under the sync queue, and in a test run', function () {
        config(['atom.job_progress.store' => 'array']);
        jpSignIn(7);

        expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();   // testing

        $this->app['env'] = 'local';
        config(['queue.default' => 'sync']);

        expect(JobProgress::for(JobProgressFixtureJob::class)->exists())->toBeFalse();
    });

    it('never fails a dispatch, a worker or a job over a broken store, and logs it once', function () {
        config(['cache.stores.nowhere' => ['driver' => 'null'], 'atom.job_progress.store' => 'nowhere']);
        Log::spy();
        jpSignIn(7);

        JobProgressFixtureJob::dispatch();
        JobProgressFixtureJob::dispatch();
        jpWork();
        (new JobProgressFixtureJob)->handle();

        expect(DB::table('jobs')->count())->toBe(1);   // the first was worked; the second is still waiting

        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldNotHaveReceived('warning', fn ($message, $context) => json_encode($context) !== false && str_contains(json_encode($context), 'journal'));
    });

    it('logs the class and where it failed, never the value', function () {
        config(['cache.stores.nowhere' => ['driver' => 'null'], 'atom.job_progress.store' => 'nowhere']);
        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $context = []) use (&$logged) {
            $logged[] = [$message, $context];
        });
        jpSignIn(7);

        (new JobProgressFixtureJob)->handle();
        (new JobProgressFixtureJob)->handle();

        expect($logged)->toHaveCount(1)
            ->and($logged[0][1]['job'])->toBe(JobProgressFixtureJob::class)
            ->and($logged[0][1]['at'])->toContain('JobProgressStore.php:')
            ->and(json_encode($logged[0][1]))->not->toContain('journal')->not->toContain('/data/exports');
    });
});

describe('the job class', function () {
    it('refuses a job that declares its own error(), naming it, at dispatch', function () {
        jpSignIn(7);

        expect(fn () => JobProgressShadowingJob::dispatch())->toThrow(LogicException::class, 'error()')
            ->and(DB::table('jobs')->count())->toBe(0);
    });

    it('refuses a shadowing job even when nobody is signed in', function () {
        expect(fn () => JobProgressShadowingJob::dispatch())->toThrow(LogicException::class, 'error()');
    });

    it('lets a job pick its own progressName(), one entry per name', function () {
        jpSignIn(7);

        JobProgressRenamedJob::dispatch('invoice');
        JobProgressRenamedJob::dispatch('payment');
        jpWork();
        jpSignIn(7);

        expect(JobProgress::for('export:invoice')->exists())->toBeTrue()
            ->and(JobProgress::for('export:payment')->isQueued())->toBeTrue()
            ->and(JobProgress::for('export:other')->exists())->toBeFalse()
            ->and(JobProgress::for(JobProgressRenamedJob::class)->exists())->toBeFalse();
    });

    it('refuses an empty name', function () {
        jpSignIn(7);

        expect(fn () => JobProgress::for(''))->toThrow(LogicException::class);
    });
});

describe('the scope', function () {
    it('throws until scopeUsing() is registered', function () {
        $this->app->forgetInstance(JobProgressStore::SCOPE_BINDING);
        jpSignIn(7);

        expect(fn () => JobProgress::for('x'))->toThrow(LogicException::class, 'scopeUsing')
            ->and(fn () => JobProgressFixtureJob::dispatch())->toThrow(LogicException::class, 'scopeUsing');
    });

    it('does not ask for a scope when no one is signed in', function () {
        $this->app->forgetInstance(JobProgressStore::SCOPE_BINDING);

        expect(JobProgress::for('x')->exists())->toBeFalse();
    });

    it('takes an int, a string or null and nothing else', function (mixed $value, bool $ok) {
        JobProgress::scopeUsing(fn () => $value);
        jpSignIn(7);

        $call = fn () => JobProgress::for('x');

        $ok ? expect($call)->not->toThrow(LogicException::class) : expect($call)->toThrow(LogicException::class, 'int, a string or null');
    })->with([
        'an int' => [3, true],
        'a string' => ['org', true],
        'null' => [null, true],
        'an array' => [[1], false],
        'a bool' => [true, false],
        'an object' => [new stdClass, false],
    ]);

    it('lets an exception from the resolver reach the caller', function () {
        JobProgress::scopeUsing(fn () => throw new RuntimeException('no org selected'));
        jpSignIn(7);

        expect(fn () => JobProgress::for('x'))->toThrow(RuntimeException::class, 'no org selected');
    });
});

it('stays out of the way of a queue job that carries no progress key', function () {
    $job = new SyncJob(app(), json_encode(['uuid' => 'u', 'job' => 'x', 'data' => []]), 'sync', 'default');

    JobProgressStore::processing($job);
    JobProgressStore::processed($job);
    JobProgressStore::failed($job);

    expect(jpEntryRows())->toBeEmpty();
});

it('ignores a payload key that is not one of ours', function () {
    $job = new SyncJob(app(), json_encode(['uuid' => 'u', 'atom:job-progress' => 'any-key-the-queue-store-was-tampered-with']), 'sync', 'default');

    JobProgressStore::processing($job);

    expect(jpEntryRows())->toBeEmpty();
});

/** A queue job built from the payload the real queue just stored, to call a hook by hand. */
function jpQueuedJob(): SyncJob
{
    return new SyncJob(app(), DB::table('jobs')->value('payload'), 'database', 'default');
}

describe('the hooks, one at a time', function () {
    beforeEach(function () {
        jpSignIn(7);
        JobProgressFixtureJob::dispatch();
        $this->key = JobProgressStore::key(JobProgressFixtureJob::class);
    });

    it('JobProcessing writes running at 0, keeps when it was queued, and restarts a retry from 0', function () {
        $this->travel(3)->seconds();

        JobProgressStore::processing(jpQueuedJob());

        $entry = JobProgress::for(JobProgressFixtureJob::class);

        expect($entry->isRunning())->toBeTrue()
            ->and($entry->done)->toBe(0)
            ->and($entry->total)->toBeNull()
            ->and($entry->startedAt)->toBeGreaterThanOrEqual($entry->queuedAt + 3);

        Cache::put($this->key, jpStored(['run' => $entry->run, 'done' => 3, 'errors' => ['old']]), 600);

        JobProgressStore::processing(jpQueuedJob());

        expect(JobProgress::for(JobProgressFixtureJob::class))
            ->done->toBe(0)
            ->errors->toBe([]);
    });

    it('JobProcessed does not turn a failed or finished entry into finished', function (string $status) {
        $run = JobProgress::for(JobProgressFixtureJob::class)->run;
        Cache::put($this->key, jpStored(['run' => $run, 'status' => $status, 'ended_at' => 5]), 600);

        JobProgressStore::processed(jpQueuedJob());

        expect(JobProgress::for(JobProgressFixtureJob::class))
            ->status->toBe($status)
            ->endedAt->toBe(5);
    })->with(['failed', 'finished']);

    it('JobProcessed finishes a queued or running entry of its own run', function (string $status) {
        $run = JobProgress::for(JobProgressFixtureJob::class)->run;
        Cache::put($this->key, jpStored(['run' => $run, 'status' => $status]), 600);

        JobProgressStore::processed(jpQueuedJob());

        expect(JobProgress::for(JobProgressFixtureJob::class))->isFinished()->toBeTrue();
    })->with(['queued', 'running']);

    it('JobFailed drops a result file the entry already named, and stores no message', function () {
        $run = JobProgress::for(JobProgressFixtureJob::class)->run;
        Cache::put($this->key, jpStored(['run' => $run, 'status' => 'finished', 'file' => '/data/x.xlsx', 'file_name' => 'x.xlsx']), 600);

        JobProgressStore::failed(jpQueuedJob());

        expect(JobProgress::for(JobProgressFixtureJob::class))
            ->isFailed()->toBeTrue()
            ->file->toBeNull()
            ->fileName->toBeNull()
            ->errors->toBe(['a']);
    });

    it('JobFailed starts a failed entry when the old one has expired', function () {
        Cache::forget($this->key);

        JobProgressStore::failed(jpQueuedJob());

        expect(JobProgress::for(JobProgressFixtureJob::class))
            ->isFailed()->toBeTrue()
            ->done->toBe(0);
    });

    it('the store caps what it is handed, whatever the caller does', function () {
        JobProgressStore::write($this->key, jpStored([
            'errors' => array_map(fn ($i) => "row {$i}", range(1, 150)),
            'error_count' => 150,
        ]));

        // the raw row, not the read: the read caps again, which would hide a write that did not
        $stored = unserialize(jpEntryRows()[0]->value, ['allowed_classes' => false]);

        expect($stored['errors'])->toHaveCount(100)
            ->and($stored['error_count'])->toBe(150);
    });
});
