@props([
    'exclusive' => false,
])

<div x-data="accordion({ exclusive: {{ $exclusive ? 'true' : 'false' }} })"
    {{ $attributes->class(['divide-y divide-zinc-200 dark:divide-zinc-700']) }}
    data-atom-accordion>
    {{ $slot }}
</div>
