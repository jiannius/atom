@props([
    'exclusive' => false,
])

<div x-data="accordion({ exclusive: {{ $exclusive ? 'true' : 'false' }} })"
    {{ $attributes->class(['divide-y divide-border']) }}
    data-atom-accordion>
    {{ $slot }}
</div>
