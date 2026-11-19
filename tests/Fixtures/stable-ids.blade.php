<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
    </div>

    {{-- no id, no name, no wire:model: the id can only come from what the markup is --}}
    <atom:accordion data-probe="accordion">
        <atom:accordion.item heading="First">Answer one</atom:accordion.item>
        <atom:accordion.item heading="Second">Answer two</atom:accordion.item>
    </atom:accordion>

    <atom:slider label="Volume" data-probe="slider"/>

    <atom:rating label="Score" data-probe="rating"/>
</div>
