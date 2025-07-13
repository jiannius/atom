@props([
    'label' => null,
])

<option {{ $attributes }} data-atom-option>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {{ t($label) }}
    @endif
</option>
