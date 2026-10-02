<atom:html title="E2E: Table Search Chip" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts tests/Fixtures/TableSearchChipFixture.php: a bound table.search beside a
     select filter in a filter bar, over a table, so the e2e can watch the search
     chip appear and "Clear all" empty the box through a real Livewire round trip. --}}
<div class="p-4">
    <livewire:atom-e2e-table-search-chip />
</div>

@livewireScripts
</atom:html>
