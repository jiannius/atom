@if ($attributes->has('href'))
    <a {{ $attributes->class(['*:w-full *:h-full *:object-contain']) }}>
        {{ $slot }}
    </a>
@else
    <figure {{ $attributes->class(['*:w-full *:h-full *:object-contain']) }}>
        {{ $slot }}
    </figure>
@endif