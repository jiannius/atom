<atom:html title="E2E: Stable Ids" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/StableIdsFixture.php) so the E2E can drive
     a real Livewire morph over an accordion, an unnamed slider and an unnamed rating. --}}
<div class="p-4">
    <livewire:atom-e2e-stable-ids />
</div>

@livewireScripts
</atom:html>
