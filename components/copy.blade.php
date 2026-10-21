@props(['value' => ''])

<div
x-data="{ value: @js($value) }"
x-on:click.stop.prevent="() => {
    let html = $el.innerHTML;
    let value = this.value || $el.getAttribute('data-copy-value')

    $clipboard(value)
        .then(() => $el.innerHTML = @js('<svg xmlns="http://www.w3.org/2000/svg" class="lucide lucide-check-icon lucide-check text-success-fg size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'))
        .then(() => setTimeout(() => $el.innerHTML = html, 700))
}"
{{ $attributes->class('contents') }}>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <atom:icon.copy class="size-4" />
    @endif
</div>
