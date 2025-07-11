@props([
    'sortable' => false,
])

<div {{ $attributes->class(['border-l border-zinc-200 px-1']) }}>
    {{ $slot }}
</div>
