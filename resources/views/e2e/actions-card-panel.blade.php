{{-- <atom:action> in a card's and a panel's heading row (tests/Fixtures/ActionsCardPanelFixture.php) --}}
<atom:html title="E2E: Actions in a card and a panel" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
<div class="p-4">
    <livewire:atom-e2e-actions-card-panel />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
