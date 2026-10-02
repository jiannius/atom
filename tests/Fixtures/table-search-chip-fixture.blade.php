<div>
    <button type="button" wire:click="resetSearch" data-reset-search>Reset from server</button>

    <atom:table :empty="false">
        <x-slot:header>
            <atom:table.filters>
                <atom:table.search wire:model="search" placeholder="Search fruit..." data-search />

                <atom:select variant="filter" wire:model.live="status" label="Status" :options="[
                    ['value' => 'fresh', 'label' => 'Fresh'],
                    ['value' => 'dried', 'label' => 'Dried'],
                ]" />
            </atom:table.filters>
        </x-slot:header>

        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Status</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($this->rows as $row)
                <atom:table.row wire:key="row-{{ $row['name'] }}">
                    <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                    <atom:table.cell>{{ $row['status'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
