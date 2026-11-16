{{-- Arrow keys in atom's menus (tests/Fixtures/MenuKeysFixture.php). The page is on the consumer's Tailwind (latest). --}}
<atom:html title="E2E: Menu keys" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
<div class="p-4">
    <livewire:atom-e2e-menu-keys />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
