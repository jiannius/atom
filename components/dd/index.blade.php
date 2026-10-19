@props([
    'label' => null,
    'block' => false,
    'filler' => '--',
])

@php
$classes = Arr::toCssClasses([
    'space-y-1',
    'md:space-y-0 md:grid md:gap-x-3 md:grid-cols-5 md:items-start' => !$block,
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-dd>
    <dt class="md:col-span-2 text-fg-muted">{{ t($label) }}</dt>
    <dd class="md:col-span-3 min-w-0 text-fg [overflow-wrap:anywhere]">{{ $slot->isEmpty() && $filler ? $filler : $slot }}</dd>
</div>