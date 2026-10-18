@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: page.header in a slide-over" :vite="[]" :dark="request()->query('dark') === '1'" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
{{-- Hosts the Livewire fixture (tests/Fixtures/PageHeaderSlideFixture.php): page.header inside an open slide-over, morphed. --}}
<div class="p-4">
    <livewire:atom-e2e-page-header-slide />
</div>

@livewireScripts
</atom:html>
