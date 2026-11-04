@php
use Jiannius\Atom\Services\JobProgress;

/* On a real page this is $this->jobProgress(ExportJournal::class). The docs are not a Livewire page, so nothing polls here. */
$queued = new JobProgress(name: 'journal', status: 'queued', run: 'a');
$running = new JobProgress(name: 'journal', status: 'running', done: 420, total: 1000, run: 'a');
$finished = new JobProgress(name: 'journal', status: 'finished', done: 1000, total: 1000, file: '/path/the/host/serves/journal.xlsx', fileName: 'journal.xlsx', run: 'a');
$partial = new JobProgress(name: 'import', status: 'finished', done: 80, total: 80, errors: ['Row 3: no line items', 'Row 9: unknown account'], errorCount: 2, run: 'b');
$failed = new JobProgress(name: 'journal', status: 'failed', done: 420, total: 1000, run: 'a');
@endphp

<div class="grid gap-6">
    <atom:progress :job="$queued" :poll="false" />
    <atom:progress :job="$running" :poll="false" />
    <atom:progress :job="$finished" :poll="false" download="download">
        <x-slot:end>
            <atom:button size="sm">New export</atom:button>
        </x-slot:end>
    </atom:progress>
    <atom:progress :job="$partial" :poll="false" />
    <atom:progress :job="$failed" :poll="false" />
</div>
