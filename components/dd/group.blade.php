@props([
    'cols' => 1,
])

<div {{ $attributes->class([match ((int) $cols) {
    1 => 'space-y-3',
    2 => 'grid md:grid-cols-2 gap-2',
    3 => 'grid md:grid-cols-3 gap-2',
    4 => 'grid md:grid-cols-4 gap-2',
    default => 'space-y-3',
}]) }} data-atom-dd-group>
    {{ $slot }}
</div>