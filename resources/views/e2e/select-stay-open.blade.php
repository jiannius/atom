@php
// ?tw=floor|latest draws the page on the consumer's Tailwind (the option list's 400px cap is a utility)
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Select Stay Open" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/SelectStayOpenFixture.php): multiple selects that stay open while values are ticked (#280). --}}
<div class="p-4">
    <livewire:atom-e2e-select-stay-open />
</div>

@livewireScripts
</atom:html>
