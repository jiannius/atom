<span {{ $attributes->class([
    str($attributes->get('class'))->is('*size-*') ? '' : 'size-6',
    '*:w-full *:h-full',
]) }} data-atom-icon>
    {{ $slot }}
</span>