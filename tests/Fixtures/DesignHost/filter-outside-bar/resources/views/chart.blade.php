{{-- No table in this view: a chart's own range is not a list's filter. --}}
<atom:page>
    <atom:page.header title="Revenue" />

    <atom:card heading="Range">
        <input type="date" wire:model.live="filters.from">
        <input type="date" wire:model.live="filters.to">
        <atom:button wire:click="$set('filters.period', 'year')">This year</atom:button>
    </atom:card>
</atom:page>
