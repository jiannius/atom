@props([
    'heading' => null,
    'scrollable' => true,
])

<div class="space-y-2" data-atom-list>
    @if ($heading)
        <div class="text-xs font-medium text-fg-muted">{{ $heading }}</div>
    @endif

    <div {{ $attributes->class([
        'border-l border-border px-1 space-y-1 overflow-auto',
        'max-h-96' => $scrollable,
    ]) }}>
        {{ $slot }}
    </div>

    {{ $actions ?? null }}
</div>
