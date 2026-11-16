{{-- extra-primary: what the browser shows in turn is not on the screen at once. Nothing here is paired. --}}
<atom:page x-data="{ step: 1 }">
    <atom:page.header title="Wizard" />

    <atom:button x-show="step === 1" variant="primary" x-on:click="step = 2">Next</atom:button>
    <atom:button x-show.transition="step === 2" variant="primary" wire:click="finish">Finish</atom:button>
    <template x-if="step === 3"><atom:button variant="primary">Done</atom:button></template>
    <div wire:show="open"><atom:button variant="primary">Open</atom:button></div>
    <atom:button :hidden="$hidden" variant="primary">Hidden</atom:button>

    <div x-show="step === 4">
        <atom:button variant="primary">Four</atom:button>
    </div>
</atom:page>
