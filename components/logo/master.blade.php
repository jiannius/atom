<atom:logo._wrapper :attributes="$attributes->except('alt', 'width', 'height')">
    <img src="https://www.mastercard.com/content/dam/public/mastercardcom/mea/za/logos/mc-logo-52.svg" {{ $attributes->merge([
        'alt' => 'Mastercard',
        'width' => 512,
        'height' => 512,
    ])->only('alt', 'width', 'height') }}>
</atom:logo._wrapper>