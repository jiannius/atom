@props([
    'label' => null,
    'block' => false,
    'filler' => '--',
])

@php
$classes = Arr::toCssClasses([
    'space-y-1',
    'md:space-y-0 md:grid md:gap-2 md:grid-cols-5 md:items-start' => !$block,
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-dd>
    <dt class="md:col-span-2 text-muted dark:text-muted-foreground">{{ t($label) }}</dt>
    <dd class="md:col-span-3">{{ $slot->isEmpty() && $filler ? $filler : $slot }}</dd>
</div>