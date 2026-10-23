@props([
    'kind' => 'event',
    'label' => null,
    'collapsible' => true,
])

{{-- A timeline of one kind, drawn from its <atom:timeline.item> children:
     - `event` (the default): a record's activity, newest first as the host orders it. Each item is a dot on a spine, with
       what happened, who and when, an optional status and an expandable detail.
     - `step`: a numbered spine for a process (a run, an onboarding): each step is done, current, pending or failed, and
       can fold its body away (`collapsible`, on by default; pass :collapsible="false" for a list that is always open).
     - `path`: a horizontal path of stages for a record that moves through them (opened, in progress, invoiced, paid): one
       segment per item, side by side in one bordered strip, each done, current, pending or failed, with a title, a time and
       a person (`actor`). It stacks as a column when its own box is narrower than 42rem (a container query, so a path in a
       narrow column stacks on a wide screen too). Not foldable: a segment has no body.
     - `message`: a conversation between two speakers, the other one at the start and yours at the end, with an optional
       composer as the last row: put it in the `composer` slot. An `<atom:input>` (bare or labelled) that is a direct child of the
     composer's <form> fills the row beside the button.
     The kind lives here, not on each item: a list is one kind. `label` names the list for a screen reader (the default
     is Activity, Steps or Messages). Order is the host's: atom never sorts or fetches. --}}
@php
$kind = in_array($kind, ['event', 'step', 'path', 'message'], true) ? $kind : 'event';

$name = $label ? t($label) : match ($kind) {
    'step' => t('Steps'),
    'path' => t('Progress'),
    'message' => t('Messages'),
    default => t('Activity'),
};
@endphp

<div {{ $attributes->class(['min-w-0', '@container w-full' => $kind === 'path']) }} data-atom-timeline data-kind="{{ $kind }}">
    <ol @class([
        'm-0 list-none p-0',
        'grid gap-4' => $kind === 'message',
        '[counter-reset:atom-step]' => $kind === 'step',
        'grid grid-cols-[minmax(0,1fr)] overflow-hidden rounded-panel border bg-surface @2xl:grid-flow-col @2xl:auto-cols-[minmax(0,1fr)] @2xl:grid-cols-none' => $kind === 'path',
    ]) aria-label="{{ $name }}" data-atom-timeline-list>
        {{ $slot }}
    </ol>

    @if (isset($composer) && $composer->isNotEmpty())
        <div {{ $composer->attributes->class('mt-4 border-t pt-4 [&_form>[data-atom-input]]:min-w-0 [&_form>[data-atom-input]]:grow [&_form>[data-atom-input-field]]:min-w-0 [&_form>[data-atom-input-field]]:grow') }} data-atom-timeline-composer>
            {{ $composer }}
        </div>
    @endif
</div>
