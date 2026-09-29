<atom:html title="E2E: Select Sibling Sync" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/SelectSiblingSyncFixture.php): one
     select's updated* hook changes a sibling select's value on the server. Shaped
     like the consumer that reported it — the component's root is the modal, opened
     from a trigger outside it. --}}
<div class="p-4">
    <atom:modal.trigger name="import">
        <atom:button data-open>Import</atom:button>
    </atom:modal.trigger>

    <livewire:atom-e2e-select-sibling-sync />
</div>

@livewireScripts
</atom:html>
