@props([
    'href' => null,
])

@php
$clickable = $href || $attributes->hasLike('x-on:click*', 'wire:click*');
@endphp

<tr 
@if ($href && $attributes->has('wire:navigate'))
    x-on:click="() => Livewire.navigate(@js($href))"
@elseif ($href)
    x-on:click="() => window.location.href = @js($href)"
@endif
{{ $attributes->class(['hover:bg-zinc-50 dark:hover:bg-zinc-700/20', $clickable ? 'cursor-pointer' : ''])->except('wire:navigate') }}
data-atom-table-row>
    {{ $slot }}
</tr>