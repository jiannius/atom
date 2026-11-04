<atom:html title="E2E: Chart Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/ChartMorphFixture.php) so the E2E can drive a real
     Livewire re-render over charts that ApexCharts has already drawn into. --}}
<div class="p-4">
    <livewire:atom-e2e-chart-morph />
</div>

@livewireScripts
</atom:html>
