@aware(['kind' => 'event', 'collapsible' => true])

@props([
    'title' => null,
    'body' => null,
    'actor' => null,
    'time' => null,
    'tone' => 'neutral',
    'status' => null,
    'state' => 'pending',
    'open' => null,
    'speaker' => null,
    'side' => 'start',
])

{{-- One row of <atom:timeline>; what it draws follows the timeline's `kind`.
     event:   `title` (what happened, bold), `body` (a line after it) or the slot, `actor` and `time` (muted, under it), `tone`
              (the dot: neutral, info, success, warning, danger or accent) and an optional `status` label (a status pill in the
              same tone). A `detail` slot is folded away behind "Show details".
     step:    `title`, `time` (a duration or a date, at the end, before the chevron that is down when closed and up when open), `state` (done, current, pending or failed), `status` (a label
              beside the title, in the state's tone; `current` pulses it), and the slot as the body, which folds away when the
              timeline is collapsible. `open` sets the starting state: a current or failed step starts open, others closed.
     path:    `title`, `state` (done, current, pending or failed), `time` and `actor` (a muted line under the title: the time, then who), and
              the slot as one more muted line. The current segment is tinted, and carries aria-current="step". `tone` (info, success,
              warning or danger) tints the CURRENT segment and its marker in the status's tone, so the path matches the record's status; without
              one (or neutral, or accent) it is a light wash of the accent.
     message: `speaker`, `time`, `side` (`start` for the other person, `end` for you) and `body` or the slot.
     Every string prints as text: the host's own markup goes in a slot, and then the host owns escaping it. A `time` may be a
     DateTimeInterface (printed in a <time> element, in the viewer's timezone) or a string. A calendar date with no time goes in as a `Y-m-d` string: as a DateTimeInterface a midnight-UTC date would show as, say, 8:00 am. Titles, actors and bodies are data, so they are not translated. --}}
@php
$kind = in_array($kind, ['event', 'step', 'path', 'message'], true) ? $kind : 'event';
$tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'danger', 'accent'], true) ? $tone : 'neutral';
$state = in_array($state, ['done', 'current', 'pending', 'failed'], true) ? $state : 'pending';
$side = $side === 'end' ? 'end' : 'start';

$text = fn ($value) => is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

// an instant is shown in the viewer's zone (the one the rest of atom reads, Carbon::getLocalTimezone()), not the server's; the machine value keeps its own offset
$timeText = $time instanceof \DateTimeInterface ? \Jiannius\Atom\Services\Carbon::instance($time)->local()->format('j M Y, g:i a') : $text($time);
$timeAttr = $time instanceof \DateTimeInterface ? $time->format(DATE_ATOM) : null;

$hasSlot = $slot->isNotEmpty();
$hasDetail = isset($detail) && $detail->isNotEmpty();

// a step's tone follows its state
$stateTone = match ($state) {
    'done' => 'success',
    'failed' => 'danger',
    'current' => 'accent',
    default => 'neutral',
};

// a current or failed step has something to show, so it starts open; a done or pending one starts folded
$startOpen = is_null($open) ? in_array($state, ['current', 'failed'], true) : (bool) $open;
$foldable = $kind === 'step' && $collapsible && $hasSlot;

$spine = 'relative before:absolute before:top-3.5 before:bottom-0 before:w-px before:bg-border-strong last:before:hidden';
@endphp

@if ($kind === 'path')
    @php
    $pathTone = in_array($tone, ['info', 'success', 'warning', 'danger'], true) ? $tone : 'accent';
    $pathMeta = array_values(array_filter([$timeText, $text($actor)], fn ($part) => $part !== ''));
    @endphp

    <li {{ $attributes->class([
        'flex min-w-0 flex-col gap-0.5 border-t px-4 py-3 first:border-t-0 @2xl:border-t-0 @2xl:border-s @2xl:first:border-s-0',
        'bg-[color-mix(in_oklab,var(--color-accent)_7%,var(--atom-surface))]' => $state === 'current' && $pathTone === 'accent',
        'bg-info-bg' => $state === 'current' && $pathTone === 'info',
        'bg-success-bg' => $state === 'current' && $pathTone === 'success',
        'bg-warning-bg' => $state === 'current' && $pathTone === 'warning',
        'bg-danger-bg' => $state === 'current' && $pathTone === 'danger',
    ]) }} @if ($state === 'current') aria-current="step" @endif data-atom-timeline-item data-state="{{ $state }}">
        <div class="flex min-w-0 items-center gap-2">
            <span @class([
                'flex size-4 shrink-0 items-center justify-center rounded-full',
                'border border-fg-subtle' => $state === 'pending',
                'border-2' => $state === 'current',
                'border-accent-ink' => $state === 'current' && $pathTone === 'accent',
                'border-info-fg' => $state === 'current' && $pathTone === 'info',
                'border-success-fg' => $state === 'current' && $pathTone === 'success',
                'border-warning-fg' => $state === 'current' && $pathTone === 'warning',
                'border-danger-fg' => $state === 'current' && $pathTone === 'danger',
                'bg-success-fg text-surface' => $state === 'done',
                'bg-danger-fg text-surface' => $state === 'failed',
            ]) data-atom-timeline-marker>
                @if ($state === 'done')
                    <atom:icon.check class="size-3" />
                    <span class="sr-only">{{ t('Done') }}</span>
                @elseif ($state === 'failed')
                    <atom:icon.close class="size-3" />
                    <span class="sr-only">{{ t('Failed') }}</span>
                @elseif ($state === 'current')
                    <span aria-hidden="true" @class(['size-1.5 rounded-full', 'bg-accent-ink' => $pathTone === 'accent', 'bg-info-fg' => $pathTone === 'info', 'bg-success-fg' => $pathTone === 'success', 'bg-warning-fg' => $pathTone === 'warning', 'bg-danger-fg' => $pathTone === 'danger'])></span>
                    <span class="sr-only">{{ t('Current') }}</span>
                @else
                    <span class="sr-only">{{ t('Pending') }}</span>
                @endif
            </span>

            <span @class(['min-w-0 truncate font-medium leading-5', 'text-fg-muted' => $state === 'pending', 'text-fg' => $state !== 'pending']) data-atom-timeline-title>{{ $text($title) }}</span>
        </div>

        @if ($pathMeta)
            <div class="min-w-0 ps-6 text-xs leading-[18px] tabular-nums [overflow-wrap:anywhere] {{ $state === 'current' ? 'text-fg-muted' : 'text-fg-subtle' }}" data-atom-timeline-meta>
                @if ($timeText !== '')
                    @if ($timeAttr)
                        <time datetime="{{ $timeAttr }}">{{ $timeText }}</time>
                    @else
                        <span>{{ $timeText }}</span>
                    @endif
                @endif

                @if ($text($actor) !== '')
                    @if ($timeText !== '')<span aria-hidden="true"> · </span>@endif
                    <span data-atom-timeline-actor>{{ $text($actor) }}</span>
                @endif
            </div>
        @endif

        @if ($hasSlot)
            <div class="min-w-0 ps-6 text-xs leading-[18px] text-fg-muted [overflow-wrap:anywhere]" data-atom-timeline-body>{{ $slot }}</div>
        @endif
    </li>
@elseif ($kind === 'message')
    <li {{ $attributes->class(['flex min-w-0 flex-col gap-1', 'items-end' => $side === 'end']) }} data-atom-timeline-item data-side="{{ $side }}">
        @if ($text($speaker) !== '' || $timeText !== '')
            <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 text-xs text-fg-subtle [overflow-wrap:anywhere]" data-atom-timeline-meta>
                @if ($text($speaker) !== '')
                    <span class="font-medium text-fg-muted" data-atom-timeline-speaker>{{ $text($speaker) }}</span>
                @endif

                @if ($timeText !== '')
                    @if ($timeAttr)
                        <time datetime="{{ $timeAttr }}">{{ $timeText }}</time>
                    @else
                        <span>{{ $timeText }}</span>
                    @endif
                @endif
            </div>
        @endif

        <div @class([
            'max-w-[85%] min-w-0 [overflow-wrap:anywhere] rounded-panel px-3.5 py-2.5 text-base text-fg',
            'bg-neutral-bg' => $side === 'start',
            'bg-accent-soft' => $side === 'end',
        ]) data-atom-timeline-bubble>
            @if ($hasSlot)
                {{ $slot }}
            @else
                <span class="whitespace-pre-line">{{ $text($body) }}</span>
            @endif
        </div>
    </li>
@elseif ($kind === 'step')
    <li
    {{ $attributes->class([$spine, 'grid grid-cols-[24px_minmax(0,1fr)] gap-x-3 pb-5 last:pb-0 before:start-[11.5px] before:top-7']) }}
    @if ($foldable) x-data="{ open: {{ $startOpen ? 'true' : 'false' }} }" @endif
    data-atom-timeline-item data-state="{{ $state }}">
        <span @class([
            'relative flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium tabular-nums [counter-increment:atom-step]',
            'bg-surface text-fg-subtle' => $state === 'pending',
            'border-transparent bg-accent text-accent-foreground' => $state === 'current',
            'border-transparent bg-success-bg text-success-fg' => $state === 'done',
            'border-transparent bg-danger-bg text-danger-fg' => $state === 'failed',
        ]) data-atom-timeline-marker>
            @if ($state === 'done')
                <atom:icon.check class="size-3.5" />
                <span class="sr-only">{{ t('Done') }}</span>
            @elseif ($state === 'failed')
                <atom:icon.close class="size-3.5" />
                <span class="sr-only">{{ t('Failed') }}</span>
            @else
                <span aria-hidden="true" class="before:content-[counter(atom-step)]"></span>
                <span class="sr-only">{{ $state === 'current' ? t('Current') : t('Pending') }}</span>
            @endif
        </span>

        <div class="min-w-0">
            @if ($foldable)
                <button type="button" class="flex w-full min-w-0 items-center gap-2 text-start" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="{{ $startOpen ? 'true' : 'false' }}" data-atom-timeline-toggle>
            @else
                <div class="flex min-w-0 items-center gap-2">
            @endif
                    <span @class(['min-w-0 truncate font-medium', 'text-fg-subtle' => $state === 'pending', 'text-fg' => $state !== 'pending']) data-atom-timeline-title>{{ $text($title) }}</span>

                    @if ($text($status) !== '')
                        <atom:status :tone="$stateTone" :running="$state === 'current'" :label="$text($status)" />
                    @endif

                    {{-- the time and the chevron are one group at the far end, so the chevron is in the same column on every step --}}
                    @if ($timeText !== '' || $foldable)
                        <span class="ms-auto flex min-w-0 max-w-[60%] items-center gap-2" data-atom-timeline-end>
                            @if ($timeText !== '')
                                <span class="min-w-0 text-xs tabular-nums text-fg-subtle [overflow-wrap:anywhere]" data-atom-timeline-time>{{ $timeText }}</span>
                            @endif

                            @if ($foldable)
                                <atom:icon.down class="size-4 shrink-0 text-fg-subtle" x-bind:class="open && 'rotate-180'" data-atom-timeline-chevron />
                            @endif
                        </span>
                    @endif
            @if ($foldable)
                </button>
            @else
                </div>
            @endif

            @if ($hasSlot)
                <div @class(['mt-1.5 min-w-0 text-sm text-fg-muted [overflow-wrap:anywhere]']) @if ($foldable) x-show="open" @if (! $startOpen) x-cloak @endif @endif data-atom-timeline-body>
                    {{ $slot }}
                </div>
            @endif
        </div>
    </li>
@else
    <li
    {{ $attributes->class([$spine, 'grid grid-cols-[16px_minmax(0,1fr)] gap-x-2.5 pb-3.5 last:pb-0 before:start-[7.5px]']) }}
    @if ($hasDetail) x-data="{ open: false }" @endif
    data-atom-timeline-item data-tone="{{ $tone }}">
        <span @class([
            'relative mt-1 ms-[3.5px] size-[9px] rounded-full',
            'bg-fg-subtle' => $tone === 'neutral',
            'bg-info-fg' => $tone === 'info',
            'bg-success-fg' => $tone === 'success',
            'bg-warning-fg' => $tone === 'warning',
            'bg-danger-fg' => $tone === 'danger',
            'bg-accent' => $tone === 'accent',
        ]) aria-hidden="true" data-atom-timeline-dot></span>

        <div class="min-w-0">
            <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                @if ($text($title) !== '')
                    <b class="min-w-0 font-medium text-fg [overflow-wrap:anywhere]" data-atom-timeline-title>{{ $text($title) }}</b>
                @endif

                @if ($hasSlot)
                    <span class="min-w-0 text-fg-muted [overflow-wrap:anywhere]">{{ $slot }}</span>
                @elseif ($text($body) !== '')
                    <span class="min-w-0 text-fg-muted [overflow-wrap:anywhere]" data-atom-timeline-body>{{ $text($body) }}</span>
                @endif

                @if ($text($status) !== '')
                    <atom:status :tone="$tone" :label="$text($status)" />
                @endif
            </div>

            @if ($text($actor) !== '' || $timeText !== '')
                <div class="mt-0.5 flex min-w-0 flex-wrap items-baseline gap-x-2 text-xs text-fg-subtle [overflow-wrap:anywhere]" data-atom-timeline-meta>
                    @if ($text($actor) !== '')
                        <span data-atom-timeline-actor>{{ $text($actor) }}</span>
                    @endif

                    @if ($timeText !== '')
                        @if ($timeAttr)
                            <time datetime="{{ $timeAttr }}">{{ $timeText }}</time>
                        @else
                            <span>{{ $timeText }}</span>
                        @endif
                    @endif
                </div>
            @endif

            @if ($hasDetail)
                <button type="button" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-fg-muted hover:text-fg" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false" data-atom-timeline-toggle>
                    <span x-show="!open">{{ t('Show details') }}</span>
                    <span x-show="open" x-cloak>{{ t('Hide details') }}</span>
                    <atom:icon.down class="size-3.5 shrink-0" x-bind:class="open && 'rotate-180'" />
                </button>

                <div class="mt-1.5 rounded-control border bg-surface-sunken px-3 py-2 text-sm text-fg-muted [overflow-wrap:anywhere]" x-show="open" x-cloak data-atom-timeline-detail>
                    {{ $detail }}
                </div>
            @endif
        </div>
    </li>
@endif
