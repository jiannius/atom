<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Jiannius\Atom\Services\JobProgress;
use Jiannius\Atom\Services\JobProgressStore;
use Jiannius\Atom\Traits\ReportsProgress;
use RuntimeException;

/**
 * A job that reports progress, in the way $mode picks: 'ok' finishes with a file, 'forgets' never calls finish(), 'throws',
 * 'fails' ($this->fail()), 'releases', 'many' (1000 progress() calls), 'errors' (150 messages), 'superseded' (a newer run
 * takes the entry mid-run) and 'supersededThrows' (the same, then throws).
 */
class JobProgressFixtureJob implements ShouldQueue
{
    use Dispatchable, Queueable, ReportsProgress;

    public int $tries = 1;

    public function __construct(public string $mode = 'ok') {}

    public function handle(): void
    {
        $this->progress(0, 4);

        switch ($this->mode) {
            case 'throws':
                $this->progress(2, 4);
                $this->error('row 3: no line items');

                throw new RuntimeException('SQLSTATE[HY000] secret-host /srv/app/secret.php');

            case 'fails':
                $this->progress(2, 4);
                $this->fail();

                return;

            case 'releases':
                $this->progress(1, 4);
                $this->release(30);

                return;

            case 'forgets':
                $this->progress(3, 4);

                return;

            case 'many':
                foreach (range(1, 1000) as $i) {
                    $this->progress($i, 1000);
                }

                return;

            case 'errors':
                foreach (range(1, 150) as $i) {
                    $this->error("row {$i}");
                }

                $this->finish();

                return;

            case 'superseded':
            case 'supersededThrows':
                JobProgressStore::write(JobProgressStore::keyFor($this), JobProgressStore::entry('a-newer-run', JobProgress::RUNNING, ['done' => 7, 'total' => 9]));

                if ($this->mode === 'supersededThrows') {
                    throw new RuntimeException('boom');
                }

                return;
        }

        $this->progress(2, 4);
        $this->error('row 3');
        $this->finish('/data/exports/journal-7.xlsx', 'journal.xlsx');
    }
}
