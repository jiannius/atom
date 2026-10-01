<div class="space-y-4">
    {{-- Each picker's bound value is echoed from the SERVER, so the e2e sees what
         reached Livewire, not just what the input shows. --}}
    <div data-probe="date">
        <atom:date-picker wire:model.live="date" />
        <span data-server>{{ $date }}</span>
    </div>

    <div data-probe="date-time">
        <atom:date-picker time wire:model.live="dateTime" />
        <span data-server>{{ $dateTime }}</span>
    </div>

    <div data-probe="range">
        <atom:date-picker variant="range" wire:model.live="range" />
        <span data-server>{{ $range }}</span>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div data-probe="deferred-date">
            <atom:date-picker wire:model="deferredDate" />
            <span data-server>{{ $deferredDate }}</span>
        </div>

        <div data-probe="deferred-range">
            <atom:date-picker variant="range" wire:model="deferredRange" />
            <span data-server>{{ $deferredRange }}</span>
        </div>

        <span data-saves>{{ $saves }}</span>
        <button type="submit" data-save>Save</button>
    </form>
</div>
