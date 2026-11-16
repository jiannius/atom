{{-- <atom:action> in a page header (tests/Fixtures/ActionsFixture.php); the `href` row goes to the landing route beside it. --}}
<atom:html title="E2E: Actions" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
<div class="p-4">
    <livewire:atom-e2e-actions />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
