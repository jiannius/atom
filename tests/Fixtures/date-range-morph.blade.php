<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
    </div>

    {{-- mirrors the smgdms repro: a range picker bound with .live, so picking a
         preset/date round-trips to the server and re-renders this component --}}
    <atom:date-picker variant="range" wire:model.live="date" data-probe="live" />
</div>
