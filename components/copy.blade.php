@props(['value' => ''])

{{-- `original` is the markup from before the first click of a burst, so a click while the tick or cross shows does not keep
     that as the icon to go back to; `timer` is the one pending restore, cleared on every click. A value of 0 is copied. --}}
<div
x-data="{ value: @js($value), original: null, timer: null }"
x-on:click.stop.prevent="() => {
    $data.original ??= $el.innerHTML
    clearTimeout($data.timer)

    let value = [null, undefined, ''].includes($data.value) ? $el.getAttribute('data-copy-value') : $data.value

    $clipboard(String(value ?? ''))
        .then(() => $el.innerHTML = @js('<svg xmlns="http://www.w3.org/2000/svg" class="lucide lucide-check-icon lucide-check text-success-fg size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'))
        .catch(() => $el.innerHTML = @js('<svg xmlns="http://www.w3.org/2000/svg" class="text-danger-fg size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" data-atom-copy-failed><path d="M6 18 18 6M6 6l12 12"/></svg>'))
        .then(() => {
            clearTimeout($data.timer)
            $data.timer = setTimeout(() => {
                $el.innerHTML = $data.original
                $data.original = null
            }, 700)
        })
}"
{{ $attributes->class('contents') }}>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <atom:icon.copy class="size-4" />
    @endif
</div>
