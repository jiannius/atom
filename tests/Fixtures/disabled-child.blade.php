<div data-child>
    <atom:input wire:model.live="v" data-child-input/>
    <button type="button" wire:click="bump" data-child-bump>Bump child</button>
    <span data-child-renders>{{ $renders }}</span>
</div>
