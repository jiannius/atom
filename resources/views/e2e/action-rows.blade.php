{{-- <atom:action> in a table row's ⋯ menu (tests/Fixtures/ActionRowsFixture.php); the rows open the landing route beside it. --}}
<atom:html title="E2E: Action Rows" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
<div class="p-4">
    <livewire:atom-e2e-action-rows />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
