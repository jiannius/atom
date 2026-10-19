@props([
    'gap' => false,
])

{{-- Joined buttons share one hairline (`-ml-px`) and keep the radius only at the group's two ends. No overflow clip, so a
     focused button's ring is not cut off; a focused one is raised (`z-10`) so its ring sits over its neighbours. --}}
<div {{ $attributes->class($gap
    ? ['group/group flex items-center flex-wrap gap-3']
    : [
        'group/group flex items-center',

        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]]:rounded-none',
        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]:first-child]:rounded-s-control',
        '[&:has([data-atom-button]:not(:only-child))_[data-atom-button]:last-child]:rounded-e-control',
        '[&_[data-atom-button]:focus-visible]:relative [&_[data-atom-button]:focus-visible]:z-10',

        '[&_[data-atom-button]:not(:first-child)]:-ml-px',
        '[&_[data-atom-dropdown]:not(:first-child)]:-ml-px',
    ]
) }}>
    {{ $slot }}
</div>
