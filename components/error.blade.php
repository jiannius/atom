@php
$errors = $attributes->get('errors');

$attrs = $attributes
    ->class(['error text-red-500 text-sm font-medium'])
    ->except('errors')
    ;
@endphp

@if ($errors)
    <div {{ $attrs }} data-atom-error>
        <ul class="list-disc list-inside">
            @foreach ($errors as $val)
                <li>{!! t($val) !!}</li>
            @endforeach
        </ul>
    </div>
@elseif ($slot->isNotEmpty())
    <div {{ $attrs }} data-atom-error>
        {{ $slot }}
    </div>
@endif
