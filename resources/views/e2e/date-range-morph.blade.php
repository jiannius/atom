<atom:html title="E2E: Date Range Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/DateRangeMorphFixture.php) so the E2E
     can drive a real Livewire re-render over a wire:ignore'd range picker. --}}
{{-- The rig ships no Tailwind: stand in for the two utilities that size the trigger,
     or its unsized icons make it ~1300px tall and the calendar opens off-screen. --}}
<style>
    [data-atom-dropdown-trigger] { position: relative; }
    [data-atom-dropdown-trigger] > .absolute { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: .5rem; }
    [data-atom-dropdown-trigger] svg { width: 1.25rem; height: 1.25rem; }
</style>
<div class="p-4">
    <livewire:atom-e2e-date-range-morph />
</div>

@livewireScripts
</atom:html>
