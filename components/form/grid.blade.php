@props([
    'cols' => 'auto',
])

@if ($cols === 'auto')
    <div class="@container">
        <div {{ $attributes->class('grid gap-6 @2xl:grid-cols-2') }}>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes->class([
        'grid gap-6 grid-cols-1',
        'md:grid-cols-2' => (string) $cols === '2',
        'md:grid-cols-3' => (string) $cols === '3',
    ]) }}>
        {{ $slot }}
    </div>
@endif
