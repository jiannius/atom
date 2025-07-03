<atom:logo._wrapper :attributes="$attributes->except('alt', 'width', 'height')">
    <img src="https://www.ipay88.com/wp-content/uploads/2021/02/ipay88-logo-white.png" {{ $attributes->merge([
        'alt' => 'iPay88',
        'width' => 512,
        'height' => 512,
    ])->only('alt', 'width', 'height') }}>
</atom:logo._wrapper>