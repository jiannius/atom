@props([
    'heading' => null,
])

<div data-atom-command-group {{ $attributes->class('py-1 [&:not(:first-child)]:mt-1') }}>
    @if ($heading)
        <div data-atom-command-heading class="px-3 py-1 text-xs font-medium text-zinc-400">{{ $heading }}</div>
    @endif

    {{ $slot }}
</div>
