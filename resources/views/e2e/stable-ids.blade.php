<atom:html title="E2E: Stable Ids" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/StableIdsFixture.php) so the E2E can drive
     a real Livewire morph over an accordion, an unnamed slider and an unnamed rating. --}}
{{-- Two siblings with the same markup: a page load numbers the second one's repeats, an update of it alone does not. --}}
<div class="p-4" data-instance="1">
    <livewire:atom-e2e-stable-ids />
</div>

<div class="p-4" data-instance="2">
    <livewire:atom-e2e-stable-ids />
</div>

@livewireScripts
</atom:html>
