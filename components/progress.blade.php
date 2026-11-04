@props([
    'value' => 0,
    'max' => 100,
    'variant' => null,
    'size' => null,
    'indeterminate' => false,
    'label' => false,
    'job' => null,
    'poll' => 2500,
    'download' => null,
])

{{-- A bar. With a `job` (a Services\JobProgress, read in the host's view or render() through $this->jobProgress()) it becomes a job's
     progress: a status row, the bar, the reported messages, and, when finished, the file name and a Download button. It polls on its own
     while the job is queued or running, and stops when it is done. value, max, variant, indeterminate and label come from the job then.
     Everything is server-rendered, no Alpine state: a morph can never strip what JS wrote. The result file's path is never printed, only
     its name; `download` is the name of the host's own Livewire action, which re-reads the path on the server and re-checks access.
     The `end` slot holds more controls after Download ("New export"). --}}
@php
$jobMode = $job !== null;

if ($jobMode && ! $job instanceof \Jiannius\Atom\Services\JobProgress) {
    throw new \InvalidArgumentException('<atom:progress :job> expects a Jiannius\Atom\Services\JobProgress, got '.get_debug_type($job).'. Read one with $this->jobProgress(Job::class) or JobProgress::for(Job::class).');
}

$hasEnd = isset($end) && $end->hasActualContent();

if ($jobMode) {
    $errored = $job->hasErrors();

    [$tone, $statusText, $variant] = match (true) {
        $job->isFailed() => ['danger', 'Failed', 'danger'],
        $job->isFinished() && $errored => ['warning', 'Done with errors', 'warning'],
        $job->isFinished() => ['success', 'Done', 'success'],
        $job->isRunning() => ['info', 'Running', null],
        default => ['neutral', 'Queued', null],
    };

    $jobPercent = $job->isFailed() ? 100 : $job->percent();
    $indeterminate = $jobPercent === null;
    $value = $jobPercent ?? 0;
    $max = 100;

    /* Next to the status: the percent while the total is known, else how many are done. Nothing while queued. */
    $progressText = match (true) {
        ! $job->isRunning() => null,
        $jobPercent !== null => $jobPercent.'%',
        $job->done > 0 => t(':count done', ['count' => number_format($job->done)]),
        default => null,
    };

    $active = $job->isActive();
    $ms = (int) $poll <= 0 ? null : max(1000, (int) $poll);   /* false and 0 turn polling off; under a second is raised to one */
    $files = $job->isFinished() && filled($job->fileName);
    $canDownload = $job->isFinished() && filled($job->file) && is_string($download) && filled($download);

    /* The node that polls. Its key changes with the run and when the run stops being active: a changed key makes the morph
       replace the node, which is what starts polling for a new run (an element that merely gains wire:poll never starts) and what
       removes the interval of the one that is done. While the run stays active the key is the same, so the node is patched in
       place and keeps polling through every re-render. */
    $trackAttributes = new \Illuminate\View\ComponentAttributeBag(array_filter([
        'wire:key' => 'atom-progress-'.md5(($job->run ?? '').($active ? ':poll' : ':done')),
        'wire:poll.'.$ms.'ms.visible' => $active && $ms ? true : null,
    ], fn ($v) => $v !== null));

    /* A bag built by hand is printed as it is: only the attributes a tag brings are escaped for it, so this value is escaped here. */
    $downloadAttributes = new \Illuminate\View\ComponentAttributeBag($canDownload ? ['wire:click' => e($download)] : []);
    $label = false;
}

$percent = max(0, min(100, (int) round($value / max((float) $max, 1) * 100)));

$showLabel = ! $jobMode && $label !== false && (! $indeterminate || is_string($label));
$labelText = is_string($label) ? t($label) : $percent.'%';

$track = Arr::toCssClasses([
    'relative w-full overflow-hidden rounded-pill bg-border-strong',
    match ($size) {
        'sm' => 'h-1',
        default => 'h-1.5',
    },
]);

$bar = Arr::toCssClasses([
    'h-full rounded-pill',
    match ($variant) {
        'success' => 'bg-success-fg',
        'warning' => 'bg-warning-fg',
        'danger', 'error' => 'bg-danger-fg',
        'info' => 'bg-info-fg',
        default => 'bg-accent-ink',
    },
]);
@endphp

@if ($jobMode && ! $job->exists())
    {{-- nothing to show, and so nothing to poll --}}
@else
<div {{ $attributes->class(['flex flex-col gap-1.5']) }} data-atom-progress @if ($jobMode) data-atom-progress-job data-state="{{ $job->status }}" @if ($active) aria-busy="true" @endif @endif>
    @if ($jobMode)
        <div class="flex items-center justify-between gap-3 text-xs text-fg-muted tabular-nums" aria-live="polite" data-atom-progress-status>
            <atom:status :tone="$tone" :running="$job->isRunning()">{{ t($statusText) }}</atom:status>

            @if ($progressText)
                <span>{{ $progressText }}</span>
            @endif
        </div>
    @elseif ($showLabel)
        <div class="flex items-center justify-between text-xs text-fg-muted tabular-nums">
            <span>{{ $labelText }}</span>
        </div>
    @endif

    <div
        {{ $jobMode ? $trackAttributes : '' }}
        class="{{ $track }}"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        @unless ($indeterminate) aria-valuenow="{{ $percent }}" @endunless>
        @if ($indeterminate)
            <div class="{{ $bar }} absolute inset-y-0 w-2/5 motion-reduce:animate-none!" style="animation: atom-progress-indeterminate 1.5s ease-in-out infinite"></div>
        @else
            <div class="{{ $bar }}" style="width: {{ $percent }}%"></div>
        @endif
    </div>

    @if ($jobMode)
        @if ($job->isFailed() || $errored)
            {{-- the messages the job reported through error(), as plain text: never an exception message --}}
            <atom:callout :variant="$job->isFailed() ? 'danger' : 'warning'" data-atom-progress-errors>
                @if ($job->isFailed())
                    <div>{{ t('The job stopped before it finished.') }}</div>
                @endif

                @if ($errored)
                    <ul class="list-disc space-y-0.5 ps-5 text-sm">
                        @foreach ($job->errors as $message)
                            <li>{{ $message }}</li>
                        @endforeach

                        @if ($job->moreErrors() > 0)
                            <li>{{ t('and :count more', ['count' => number_format($job->moreErrors())]) }}</li>
                        @endif
                    </ul>
                @endif
            </atom:callout>
        @endif

        @if ($files || $canDownload || $hasEnd)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2" data-atom-progress-result>
                @if ($files)
                    <span class="min-w-0 max-w-full truncate text-sm text-fg-muted" data-atom-progress-file>{{ $job->fileName }}</span>
                @endif

                @if ($canDownload)
                    <atom:button size="sm" icon="download" :attributes="$downloadAttributes" data-atom-progress-download>{{ t('Download') }}</atom:button>
                @endif

                @if ($hasEnd)
                    {{ $end }}
                @endif
            </div>
        @endif
    @endif
</div>
@endif
