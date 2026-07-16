@props([
    'heading' => null,
])

@php
$headingId = 'atom-command-group-'.Str::random(8);
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
