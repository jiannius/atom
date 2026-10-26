@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: toast above the save bar" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="request()->query('dark') === '1'">
{{-- Hosts the Livewire fixture (tests/Fixtures/FormToastBarFixture.php): a page form with a pinned save bar and the toast.
     `?no-bar=1` swaps the bar for a plain footer (the baseline), `?short=1` makes the page fit the screen, `?inside=1`
     puts the toast inside the component (so a morph reaches its root). --}}
<livewire:atom-e2e-form-toast-bar :bar="request('no-bar') !== '1'" :short="request('short') === '1'" :toast-inside="request('inside') === '1'" />

@if (request('inside') !== '1')
    <atom:toast />
@endif

@livewireScripts
</atom:html>
