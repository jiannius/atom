<atom:html title="E2E: Input Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/InputMorphFixture.php) so the E2E can
     drive a real Livewire morph over labelled form controls. --}}
<div class="p-4">
    <livewire:atom-e2e-input-morph />
</div>

@livewireScripts
</atom:html>
