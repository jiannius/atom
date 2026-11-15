@props([
    'label' => null,
    'block' => false,
    'filler' => '—',
])

{{-- A value is there only if the slot holds more than comments and whitespace (hasActualContent), so a value written as an `@if`
     that comes out false in a Livewire view, which still prints its `<!--[if BLOCK]-->` markers, is empty. An empty one draws
     the missing-value mark; `filler="n/a"` prints that text instead, and `filler=""` or `:filler="false"` prints nothing. --}}
@php
$classes = Arr::toCssClasses([
    'space-y-1',
    'md:space-y-0 md:grid md:gap-x-3 md:grid-cols-5 md:items-start' => !$block,
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-dd>
    <dt class="md:col-span-2 text-fg-muted">{{ t($label) }}</dt>
    <dd class="md:col-span-3 min-w-0 text-fg [overflow-wrap:anywhere]">@if ($slot->hasActualContent()){{ $slot }}@elseif ($filler === '—')<atom:_missing />@elseif ($filler){{ $filler }}@endif</dd>
</div>