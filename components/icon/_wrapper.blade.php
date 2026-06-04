<span {{ $attributes->class([
    'inline-flex shrink-0',
    str($attributes->get('class'))->is('*size-*') ? '' : 'size-5',
    '*:w-full *:h-full',
]) }} data-atom-icon>
    {{ $slot }}
</span>