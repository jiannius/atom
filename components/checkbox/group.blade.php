@props([
    'variant' => null,
    'subtle' => false,
])

@php
// the key a tile group publishes for its options (TagCompiler, ChoiceTile) is not an HTML attribute
$attributes = $attributes->except(\Jiannius\Atom\Services\ChoiceTile::KEY);
@endphp

@if ($variant === 'card')
    <atom:card :subtle="$subtle" inset>
        <div {{ $attributes->class(['flex flex-col divide-y divide-border [&>[data-atom-checkbox]]:py-3 [&>[data-atom-checkbox]]:px-4']) }}>
            {{ $slot }}
        </div>
    </atom:card>
@elseif ($variant === 'tile')
    <div {{ $attributes->class('group/group') }} data-atom-tiles>
        {{ $slot }}
    </div>
@else
    <div {{ $attributes->class(['group/group flex flex-col gap-2 [&>[data-atom-heading]]:mb-1']) }}>
        {{ $slot }}
    </div>
@endif
