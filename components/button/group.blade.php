@props([
    'gap' => false,
])

<div {{ $attributes->class($gap
    ? ['group/group flex items-center flex-wrap gap-3']
    : [
        'group/group flex items-center rounded-md overflow-hidden',
        '[&_[data-atom-button]]:-ml-px',
        '[&_[data-atom-button]]:rounded-none',
    ]
) }}>
    {{ $slot }}
</div>
