@if ($attributes->has('href'))
    <a {{ $attributes->except('href')->class(['*:w-full *:h-full *:object-contain'])->merge(['href' => safe_url($attributes->get('href'))], false) }}>
        {{ $slot }}
    </a>
@else
    <figure {{ $attributes->class(['*:w-full *:h-full *:object-contain']) }}>
        {{ $slot }}
    </figure>
@endif