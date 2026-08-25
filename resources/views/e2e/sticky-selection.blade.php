<atom:html title="E2E: Sticky Selection" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/StickySelectionFixture.php) so the
     E2E can drive a real filter change over a live checkbox selection. --}}
{{-- the rig serves no Tailwind, so the checkbox would render as a zero-size box
     that Playwright can never click. Give it a real one. --}}
<style>
    [data-atom-table-checkbox] { width: 20px; height: 20px; border: 1px solid #999; }
</style>

<div class="p-4">
    <livewire:atom-e2e-sticky-selection />
</div>

@livewireScripts
</atom:html>
