@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Disabled behaviour" :vite="[]" :dark="false" fonts="geist" :styles="['/atom/e2e/consumer-css/'.$tw]">
{{-- Hosts tests/Fixtures/DisabledBehaviourFixture.php: one corpus case per page, on the consumer's Tailwind. --}}
<div class="bg-surface p-6">
    <livewire:atom-e2e-disabled-behaviour />
</div>

@livewireScripts
</atom:html>
