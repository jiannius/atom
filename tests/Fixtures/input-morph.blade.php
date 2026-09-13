<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
    </div>

    {{-- .live re-renders the component from the field's own keystrokes, which is where
         a churning id costs the user their focus mid-typing --}}
    <atom:input label="Live" wire:model.live="live" data-probe="live" />

    {{-- clearable caches the input node in x-init, so it breaks silently if the node
         it cached is swapped out from under it --}}
    <atom:input label="Search" clearable wire:model="search" data-probe="clearable" />

    {{-- the tel widget holds its dial code in Alpine state that never reaches the
         server, so a replaced wrapper loses the user's pick outright --}}
    <atom:input type="tel" label="Phone" wire:model="phone" data-probe="tel" />

    <atom:textarea label="Notes" wire:model="notes" data-probe="notes" />
</div>
