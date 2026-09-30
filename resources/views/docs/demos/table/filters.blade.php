<div x-data class="space-y-8">
    <atom:table.filters>
        <atom:table.search placeholder="Search" />
        <atom:select variant="filter" wire:model="filters.status" label="Status" :options="[
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ]" />
        <atom:select variant="filter" wire:model="filters.type" label="Type" :options="[
            ['value' => 'a', 'label' => 'Type A'],
            ['value' => 'b', 'label' => 'Type B'],
        ]" />
        {{-- the default overflow: a popover, opened from the button that follows the
             last filter in the bar --}}
        <x-slot:more>
            <atom:select variant="filter" wire:model="filters.category" label="Category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
            ]" />
        </x-slot:more>
    </atom:table.filters>

    <atom:table.filters overflow="card">
        <atom:table.search placeholder="Search" />
        <x-slot:more>
            <atom:select variant="filter" wire:model="filters.category" label="Category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
            ]" />
        </x-slot:more>
    </atom:table.filters>

    <atom:table.filters overflow="modal">
        <atom:table.search placeholder="Search" />
        <x-slot:more>
            {{-- the modal lays the slot out as a form, one field per row — so write
                 fields, not bar controls: a label prop turns any atom input into a
                 labelled field. Add table-filter and the field registers a chip in
                 the bar too, named by its label; without it the field still filters,
                 it just does so invisibly. --}}
            <atom:select label="Category" variant="listbox" table-filter wire:model="filters.category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
                ['value' => 'y', 'label' => 'Category Y'],
            ]" />
            <atom:date-picker variant="range" label="Created between" wire:model="filters.dates" />
        </x-slot:more>
    </atom:table.filters>
</div>
