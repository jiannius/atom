@props(['value' => ''])

<div
x-data="{ value: @js($value) }"
x-on:click.stop.prevent="() => {
    let html = $el.innerHTML;
    let value = $data.value || $el.getAttribute('data-copy-value')

    $clipboard(value)
        .then(() => $el.innerHTML = @js('<svg xmlns="http://www.w3.org/2000/svg" class="lucide lucide-check-icon lucide-check text-success-fg size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'))
        .catch(() => $el.innerHTML = @js('<svg xmlns="http://www.w3.org/2000/svg" class="text-danger-fg size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" data-atom-copy-failed><path d="M6 18 18 6M6 6l12 12"/></svg>'))
        .then(() => setTimeout(() => $el.innerHTML = html, 700))
}"
{{ $attributes->class('contents') }}>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <atom:icon.copy class="size-4" />
    @endif
</div>
