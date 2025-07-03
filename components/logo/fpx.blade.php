<atom:logo._wrapper :attributes="$attributes->except('alt', 'width', 'height')">
    <img src="https://www.bankislam.com/wp-content/uploads/FPX-Logo.jpg" {{ $attributes->merge([
        'alt' => 'FPX',
        'width' => 512,
        'height' => 512,
    ])->only('alt', 'width', 'height') }}>
</atom:logo._wrapper>