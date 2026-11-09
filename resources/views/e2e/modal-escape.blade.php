<atom:html title="E2E: Modal Escape" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/ModalEscapeFixture.php): the modals the Escape specs (#174) open with the focus on <body>, in a select, on a button a morph removes. --}}
<livewire:atom-e2e-modal-escape />

@livewireScripts
</atom:html>
