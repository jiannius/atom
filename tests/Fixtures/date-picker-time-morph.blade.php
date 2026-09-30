<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
    </div>

    {{-- mirrors the smgdms repro: a time-flagged single date-picker bound with .live, so
         picking a day round-trips to the server while the panel stays open (the time
         flag keeps the popover open after a date pick, unlike the plain date-only
         variant which closes itself immediately) --}}
    <atom:date-picker time wire:model.live="date" data-probe="live" />
</div>
