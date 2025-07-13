@props([
    'gap' => false,
])

<div {{ $attributes->class($gap
    ? ['group/group flex items-center flex-wrap gap-3']
    : [
        'group/group flex items-center *:rounded-none',
        '*:-ml-px first:*:ml-0',
        'first:*:rounded-l-md last:*:rounded-r-md',
    ]
) }}>
    {{ $slot }}
</div>
