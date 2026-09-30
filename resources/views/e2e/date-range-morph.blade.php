<atom:html title="E2E: Date Range Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/DateRangeMorphFixture.php) so the E2E
     can drive a real Livewire re-render over a wire:ignore'd range picker. --}}
<div class="p-4">
    <livewire:atom-e2e-date-range-morph />
</div>

@livewireScripts
</atom:html>
