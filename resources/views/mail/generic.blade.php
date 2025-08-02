<x-mail::message>
{!! $content !!}

@if ($cta)
<x-mail::button :url="data_get($cta, 'url')">
{!! data_get($cta, 'label') !!}
</x-mail::button>
@endif
</x-mail::message>
