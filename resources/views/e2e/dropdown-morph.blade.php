<atom:html title="E2E: Dropdown Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/DropdownMorphFixture.php) so the E2E can
     drive a real Livewire re-render over dropdown-based panels while they are open
     (issue #56). --}}
<div class="p-4">
    <livewire:atom-e2e-dropdown-morph />
</div>

@livewireScripts
</atom:html>
