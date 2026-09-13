@props([
    'heading' => null,
])

@php
// Derived from the heading rather than randomised: an id is Livewire's morph key when
// nothing else keys the element, so one that changes per render makes the morph replace
// the group instead of patching it. Same reason as ComponentAttributeBag::fieldId().
$headingId = $heading ? 'atom-command-group-'.substr(md5($heading), 0, 8) : null;
@endphp

<div
data-atom-command-group
role="group"
@if ($heading) aria-labelledby="{{ $headingId }}" @endif
{{ $attributes->class('py-1 [&:not(:first-child)]:mt-1') }}>
    @if ($heading)
        <div id="{{ $headingId }}" data-atom-command-heading class="px-3 py-1 text-xs font-medium text-zinc-400">{{ $heading }}</div>
    @endif

    {{ $slot }}
</div>
