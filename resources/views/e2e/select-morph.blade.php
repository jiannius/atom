<atom:html title="E2E: Select Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/SelectMorphFixture.php) so the
     E2E can drive a real Livewire morph over a listbox. --}}
<div class="p-4">
    <livewire:atom-e2e-select-morph />
</div>

@livewireScripts
</atom:html>
