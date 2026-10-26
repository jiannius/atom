<div>
    <button type="button" wire:click="bump" data-testid="bump">Bump {{ $ticks }}</button>

    <atom:table :paginate="$plain ? null : $items" frameless :empty="false">
        <x-slot:header>
            <atom:table.toolbar :count="$count">
                <atom:table.search wire:model.live.debounce.150ms="filters.search" placeholder="Search items" />

                <atom:table.filters chips="inline">
                    <atom:select variant="filter" add wire:model.live="filters.status" label="Status" :options="[
                        ['value' => 'open', 'label' => 'Open'],
                        ['value' => 'closed', 'label' => 'Closed'],
                        ['value' => 'hold', 'label' => 'On hold'],
                    ]" />
                    <atom:select variant="filter" add multiple wire:model.live="filters.tags" label="Tag" :options="[
                        ['value' => 'a', 'label' => 'Tag A'],
                        ['value' => 'b', 'label' => 'Tag B'],
                        ['value' => 'c', 'label' => 'Tag C'],
                        ['value' => 'd', 'label' => 'Tag D'],
                    ]" />
                    <atom:date-picker variant="range" add wire:model.live="filters.issued" placeholder="Issued" />

                    <x-slot:more>
                        <atom:select variant="filter" add wire:model.live="filters.region" label="Region" :options="[
                            ['value' => 'n', 'label' => 'North'],
                            ['value' => 's', 'label' => 'South'],
                        ]" />
                    </x-slot:more>
                </atom:table.filters>

                <x-slot:end>
                    <atom:button size="sm" variant="ghost" icon="download">Export</atom:button>
                </x-slot:end>
            </atom:table.toolbar>
        </x-slot:header>

        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Status</atom:table.column>
            <atom:table.column>Tag</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($items as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                    <atom:table.cell>{{ $row['status'] }}</atom:table.cell>
                    <atom:table.cell>{{ $row['tag'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
