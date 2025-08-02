@props([
    'gap' => false,
])

<div {{ $attributes->class($gap
    ? ['group/group flex items-center flex-wrap gap-3']
    : [
        'group/group flex items-center rounded-md overflow-hidden',
        '[&:has([data-atom-button]:not(:only-child))]:shadow-sm',

        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]]:rounded-none',
        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]]:shadow-none',
        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]]:focus:outline-none',

        '[&_[data-atom-button]:not(:first-child)]:-ml-px',
        '[&_[data-atom-dropdown]:not(:first-child)]:-ml-px',
    ]
) }}>
    {{ $slot }}
</div>
