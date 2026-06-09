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
    </atom:table.filters>

    <atom:table.filters overflow="card">
        <atom:table.search placeholder="Search" />
        <x-slot:more>
            <atom:select variant="filter" wire:model="filters.category" label="Category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
            ]" />
        </x-slot:more>
    </atom:table.filters>
</div>
