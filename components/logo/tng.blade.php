<atom:logo._wrapper :attributes="$attributes->except('alt', 'width', 'height')">
    <img src="https://www.touchngo.com.my/assets/logos/tngd-logo.svg" {{ $attributes->merge([
        'alt' => 'Mastercard',
        'width' => 512,
        'height' => 512,
    ])->only('alt', 'width', 'height') }}>
</atom:logo._wrapper>