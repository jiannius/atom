@php
$labelled = $attributes->hasAny(['aria-label', 'aria-labelledby', 'title', 'role']);
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0',
    str($attributes->get('class'))->is('*size-*') ? '' : 'size-5',
    '*:w-full *:h-full',
])->merge(['aria-hidden' => $labelled ? null : 'true']) }} data-atom-icon>
    {{ $slot }}
</span>