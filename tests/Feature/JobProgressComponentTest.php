<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Jiannius\Atom\Services\JobProgress;
use Jiannius\Atom\Services\JobProgressStore;
use Jiannius\Atom\Tests\Fixtures\JobProgressFixture;
use Jiannius\Atom\Traits\WithJobProgress;
use Livewire\Livewire;

// <atom:progress :job> over a Services\JobProgress (#115): the four states, the polling node, and the Livewire page that reads one through
// the protected WithJobProgress helpers. The job side (queue hooks, key, store) is JobProgressJobTest.

/** A JobProgress for the component to draw. */
function jpEntry(string $status, array $with = []): JobProgress
{
    return new JobProgress(...array_merge(['name' => 'x', 'status' => $status, 'run' => 'run-1'], $with));
}

function jpRender(JobProgress $job, string $attributes = '', string $slots = ''): string
{
    return renderBlade('<atom:progress :job="$job" '.$attributes.'>'.$slots.'</atom:progress>', ['job' => $job]);
}

/** The progress track: the element that polls. */
function jpTrack(string $html): DOMElement
{
    return domQuery($html, '//*[@role="progressbar"]')[0];
}

/** The cache entry of the fixture user's export, written the way a job writes it. */
function jpSeed(string $status, array $with = [], int|string $user = 7, string $name = 'export:invoice'): void
{
    Auth::setUser(new GenericUser(['id' => $user]));

    JobProgressStore::write(JobProgressStore::key($name), JobProgressStore::entry('run-1', $status, $with));
}

beforeEach(function () {
    config(['cache.default' => 'array']);

    JobProgress::scopeUsing(fn () => 'org-1');
});

describe('the four states', function () {
    it('shows queued as an indeterminate bar that polls', function () {
        $html = jpRender(jpEntry('queued'));
        $track = jpTrack($html);

        expect($html)->toContain('data-atom-progress-job')->toContain('data-state="queued"')->toContain('aria-busy="true"')
            ->and($track->hasAttribute('aria-valuenow'))->toBeFalse()
            ->and($track->hasAttribute('wire:poll.2500ms.visible'))->toBeTrue()
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('neutral')
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->textContent)->toContain('Queued');
    });

    it('shows running with a total as a determinate bar and its percent', function () {
        $html = jpRender(jpEntry('running', ['done' => 21, 'total' => 50]));

        expect(jpTrack($html)->getAttribute('aria-valuenow'))->toBe('42')
            ->and($html)->toContain('width: 42%')->toContain('42%')
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('info')
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->hasAttribute('data-running'))->toBeTrue();
    });

    it('shows running without a total as an indeterminate bar and a count', function () {
        $html = jpRender(jpEntry('running', ['done' => 1234]));

        expect(jpTrack($html)->hasAttribute('aria-valuenow'))->toBeFalse()
            ->and($html)->toContain('1,234 done');
    });

    it('shows running at 0 with no total as just the bar', function () {
        expect(jpRender(jpEntry('running')))->not->toContain(' done')->not->toContain('0%');
    });

    it('shows finished as a full success bar, the file name and Download, and stops polling', function () {
        $html = jpRender(jpEntry('finished', ['done' => 4, 'total' => 4, 'file' => '/srv/data/exports/secret-7.xlsx', 'fileName' => 'journal.xlsx']), 'download="download"');

        expect(jpTrack($html)->getAttribute('aria-valuenow'))->toBe('100')
            ->and(jpTrack($html)->hasAttribute('wire:poll.2500ms.visible'))->toBeFalse()
            ->and($html)->not->toContain('wire:poll')->not->toContain('aria-busy')
            ->and($html)->toContain('bg-success-fg')->toContain('journal.xlsx')
            ->and(domQuery($html, '//button[@*[name()="wire:click"]="download"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('success');
    });

    it('never prints the path of the result file', function () {
        $html = jpRender(jpEntry('finished', ['file' => '/srv/data/exports/secret-7.xlsx', 'fileName' => 'journal.xlsx']), 'download="download"');

        expect($html)->not->toContain('/srv/data')->not->toContain('secret-7');
    });

    it('shows finished with errors in the warning tone, with the list', function () {
        $html = jpRender(jpEntry('finished', ['done' => 4, 'total' => 4, 'errors' => ['row 3: no lines', 'row 9: bad date'], 'errorCount' => 2]));

        expect(domQuery($html, '//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('warning')
            ->and($html)->toContain('Done with errors')->toContain('bg-warning-fg')
            ->and(array_map(fn ($li) => $li->textContent, domQuery($html, '//*[@data-atom-progress-errors]//li')))->toBe(['row 3: no lines', 'row 9: bad date']);
    });

    it('shows failed as a full danger bar and a generic line, never a message', function () {
        $html = jpRender(jpEntry('failed', ['done' => 2, 'total' => 4]));

        expect(jpTrack($html)->getAttribute('aria-valuenow'))->toBe('100')
            ->and($html)->toContain('bg-danger-fg')->toContain('The job stopped before it finished.')
            ->and(domQuery($html, '//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('danger')
            ->and($html)->not->toContain('wire:poll');
    });

    it('shows the reported messages under a failed job too', function () {
        $html = jpRender(jpEntry('failed', ['errors' => ['row 3: no lines'], 'errorCount' => 1]));

        expect($html)->toContain('The job stopped before it finished.')->toContain('row 3: no lines');
    });

    it('renders nothing for no entry, and so polls nothing', function () {
        expect(trim(jpRender(new JobProgress('x'))))->toBe('');
    });

    it('counts "and N more" past the kept messages', function () {
        $html = jpRender(jpEntry('finished', ['errors' => array_map(fn ($i) => "row {$i}", range(1, 100)), 'errorCount' => 150]));

        expect(domQuery($html, '//*[@data-atom-progress-errors]//li'))->toHaveCount(101)
            ->and($html)->toContain('and 50 more');
    });

    it('honours size, and takes its other props from the job', function () {
        $html = jpRender(jpEntry('running', ['done' => 1, 'total' => 2]), 'size="sm" variant="danger" :indeterminate="true" :value="99" label="Custom"');

        expect(domClasses(jpTrack($html)))->toContain('h-1')
            ->and($html)->toContain('width: 50%')->not->toContain('bg-danger-fg')->not->toContain('Custom');
    });

    it('refuses something that is not a JobProgress', function () {
        try {
            renderBlade('<atom:progress :job="$job" />', ['job' => ['status' => 'running']]);
        } catch (Throwable $e) {
            // the render is inside the view, so the exception arrives wrapped
            while ($e->getPrevious()) {
                $e = $e->getPrevious();
            }
        }

        expect($e)->toBeInstanceOf(InvalidArgumentException::class)->and($e->getMessage())->toContain('JobProgress');
    });
});

describe('the download button', function () {
    it('names the host action, and only when finished with a file', function (string $status, array $with, string $attributes, int $buttons) {
        $html = jpRender(jpEntry($status, $with), $attributes);

        expect(domQuery($html, '//button[@*[name()="wire:click"]]'))->toHaveCount($buttons);
    })->with([
        'finished with a file' => ['finished', ['file' => '/f', 'fileName' => 'f.xlsx'], 'download="download"', 1],
        'finished, no download prop' => ['finished', ['file' => '/f', 'fileName' => 'f.xlsx'], '', 0],
        'finished, no file' => ['finished', [], 'download="download"', 0],
        'running with a file' => ['running', ['file' => '/f'], 'download="download"', 0],
        'failed' => ['failed', ['file' => '/f'], 'download="download"', 0],
    ]);
});

describe('polling', function () {
    it('polls while queued or running and not after', function (string $status, bool $polls) {
        expect(jpTrack(jpRender(jpEntry($status)))->hasAttribute('wire:poll.2500ms.visible'))->toBe($polls);
    })->with([['queued', true], ['running', true], ['finished', false], ['failed', false]]);

    it('takes its interval from poll, raises anything under a second, and is turned off by false or 0', function (string $attributes, ?string $poll) {
        $track = jpTrack(jpRender(jpEntry('running'), $attributes));

        $polling = collect($track->attributes)->map(fn ($a) => $a->name)->filter(fn ($n) => str_starts_with($n, 'wire:poll'))->values()->all();

        expect($polling)->toBe($poll === null ? [] : [$poll]);
    })->with([
        'the default' => ['', 'wire:poll.2500ms.visible'],
        'five seconds' => [':poll="5000"', 'wire:poll.5000ms.visible'],
        'half a second' => [':poll="500"', 'wire:poll.1000ms.visible'],
        'false' => [':poll="false"', null],
        'zero' => [':poll="0"', null],
    ]);

    it('puts a new key on the polling node for a new run, and when the run stops being active', function () {
        $key = fn (string $status, string $run) => jpTrack(jpRender(jpEntry($status, ['run' => $run])))->getAttribute('wire:key');

        expect($key('running', 'a'))->toBe($key('queued', 'a'))   // the same run staying active is patched in place
            ->and($key('running', 'a'))->not->toBe($key('finished', 'a'))
            ->and($key('running', 'a'))->not->toBe($key('running', 'b'))
            ->and($key('finished', 'a'))->toBe($key('failed', 'a'));
    });
});

describe('the end slot', function () {
    it('renders after Download', function () {
        $html = jpRender(jpEntry('finished', ['file' => '/f', 'fileName' => 'f.xlsx']), 'download="download"', '<x-slot:end><button data-end>New export</button></x-slot:end>');

        expect(domQuery($html, '//*[@data-atom-progress-result]/button[@data-end]'))->toHaveCount(1);
    });

    it('draws no result row when there is nothing to put in it', function () {
        expect(domQuery(jpRender(jpEntry('finished')), '//*[@data-atom-progress-result]'))->toBeEmpty();
    });
});

it('leaves a bar without a job exactly as it was', function () {
    $html = renderBlade('<atom:progress :value="50" label />');

    expect($html)->not->toContain('data-atom-progress-job')->not->toContain('wire:poll')->not->toContain('wire:key')
        ->and($html)->toContain('aria-valuenow="50"')->toContain('50%');
});

describe('a Livewire page', function () {
    it('shows the signed-in user\'s entry, and polls only while it is active', function () {
        jpSeed('running', ['done' => 1, 'total' => 4]);

        $test = Livewire::test(JobProgressFixture::class);

        expect($test->html())->toContain('data-state="running"')->toContain('wire:poll.1000ms.visible')->toContain('25%');

        jpSeed('finished', ['done' => 4, 'total' => 4, 'file' => '/srv/data/exports/secret-7.xlsx', 'file_name' => 'journal.xlsx']);

        $test->call('again');

        expect($test->html())->toContain('data-state="finished"')->not->toContain('wire:poll');
    });

    it('never puts the file path in the page, the snapshot or an update response', function () {
        jpSeed('finished', ['file' => '/srv/data/exports/secret-7.xlsx', 'file_name' => 'journal.xlsx']);

        $test = Livewire::test(JobProgressFixture::class);
        $test->call('again');

        expect($test->html())->not->toContain('secret-7')
            ->and(json_encode($test->snapshot))->not->toContain('secret-7');
    });

    it('shows nothing of another user\'s entry, of another scope\'s, or when signed out', function () {
        jpSeed('running', [], user: 8);
        Auth::setUser(new GenericUser(['id' => 7]));

        expect(Livewire::test(JobProgressFixture::class)->html())->toContain('data-testid="start"');

        jpSeed('running');
        JobProgress::scopeUsing(fn () => 'org-2');

        expect(Livewire::test(JobProgressFixture::class)->html())->toContain('data-testid="start"');
    });

    it('lets a client-writable property that feeds the name only reach the user\'s own entries', function () {
        jpSeed('running', [], user: 8, name: 'export:payment');
        jpSeed('running', [], user: 7, name: 'export:invoice');

        $test = Livewire::test(JobProgressFixture::class);

        expect($test->html())->toContain('data-state="running"');

        $test->set('doctype', 'payment');   // user 8's entry has this name; user 7 has none

        expect($test->html())->toContain('data-testid="start"');
    });

    it('forgets the entry from the host\'s own action, and renders nothing and polls nothing after', function () {
        jpSeed('finished', ['file' => '/f', 'file_name' => 'journal.xlsx']);

        $test = Livewire::test(JobProgressFixture::class)->assertSee('journal.xlsx')->call('dismiss');

        expect($test->html())->toContain('data-testid="start"')->not->toContain('wire:poll')->not->toContain('journal.xlsx');
    });

    it('runs the host\'s download action, which reads the path on the server', function () {
        jpSeed('finished', ['file' => '/srv/data/x.xlsx', 'file_name' => 'journal.xlsx']);

        Livewire::test(JobProgressFixture::class)->call('download')->assertSet('downloaded', 'journal.xlsx');
    });

    it('refuses the download for an entry that is not finished', function () {
        jpSeed('running', ['file' => '/srv/data/x.xlsx']);

        Livewire::test(JobProgressFixture::class)->call('download')->assertStatus(404)->assertSet('downloaded', '');
    });

    it('reads fresh on every render: the entry changing between two renders is seen', function () {
        jpSeed('queued');

        $test = Livewire::test(JobProgressFixture::class);
        expect($test->html())->toContain('data-state="queued"');

        jpSeed('failed');
        $test->call('again');

        expect($test->html())->toContain('data-state="failed"')->not->toContain('wire:poll');
    });

    it('does not accept the object in a public property', function () {
        $component = new class extends \Livewire\Component {
            use WithJobProgress;

            public $export;

            public function render()
            {
                return '<div></div>';
            }
        };

        jpSeed('running');

        expect(fn () => Livewire::test($component::class)->set('export', JobProgress::for('export:invoice')))->toThrow('Property type not supported');
    });
});

it('has a docs demo that draws every state, wired into the progress docs page', function () {
    $html = view('atom::docs.demos.progress.job')->render();

    expect(domQuery($html, '//*[@data-atom-progress-job]'))->toHaveCount(5)
        ->and(array_map(fn ($el) => $el->getAttribute('data-state'), domQuery($html, '//*[@data-atom-progress-job]')))->toBe(['queued', 'running', 'finished', 'finished', 'failed'])
        ->and($html)->not->toContain('wire:poll')->not->toContain('/path/the/host/serves');

    expect(file_get_contents(__DIR__.'/../../resources/views/docs/demos/progress.blade.php'))->toContain('docs.demos.progress.job');
});
