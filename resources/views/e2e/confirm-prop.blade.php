{{-- The `confirm` prop of <atom:button> and <atom:menu.item> (tests/Fixtures/ConfirmPropFixture.php); the `href` buttons go to the landing route beside it. --}}
<atom:html title="E2E: Confirm prop" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
<div class="p-4">
    <livewire:atom-e2e-confirm-prop />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
