@props([
    'kind' => 'event',
    'label' => null,
    'collapsible' => true,
])

{{-- A vertical timeline of one kind, drawn from its <atom:timeline.item> children:
     - `event` (the default): a record's activity, newest first as the host orders it. Each item is a dot on a spine, with
       what happened, who and when, an optional status and an expandable detail.
     - `step`: a numbered spine for a process (a run, an onboarding): each step is done, current, pending or failed, and
       can fold its body away (`collapsible`, on by default; pass :collapsible="false" for a list that is always open).
     - `message`: a conversation between two speakers, the other one at the start and yours at the end, with an optional
       composer as the last row: put it in the `composer` slot.
     The kind lives here, not on each item: a list is one kind. `label` names the list for a screen reader (the default
     is Activity, Steps or Messages). Order is the host's: atom never sorts or fetches. --}}
@php
$kind = in_array($kind, ['event', 'step', 'message'], true) ? $kind : 'event';

$name = $label ? t($label) : match ($kind) {
    'step' => t('Steps'),
    'message' => t('Messages'),
    default => t('Activity'),
};
@endphp

<div {{ $attributes->class('min-w-0') }} data-atom-timeline data-kind="{{ $kind }}">
    <ol @class([
        'm-0 list-none p-0',
        'grid gap-4' => $kind === 'message',
        '[counter-reset:atom-step]' => $kind === 'step',
    ]) aria-label="{{ $name }}" data-atom-timeline-list>
        {{ $slot }}
    </ol>

    @if (isset($composer) && $composer->isNotEmpty())
        <div {{ $composer->attributes->class('mt-4 border-t pt-4') }} data-atom-timeline-composer>
            {{ $composer }}
        </div>
    @endif
</div>
