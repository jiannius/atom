<div>
    <atom:table :sticky-selection="true" :empty="false">
        <x-slot:header>
            <atom:table.search wire:model="search" data-search />
        </x-slot:header>

        <x-slot:checked>
            <button type="button" wire:click="report" data-report>Report</button>
        </x-slot:checked>

        <x-slot:columns>
            <atom:table.column checkbox />
            <atom:table.column>Name</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($this->rows as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell :checkbox="$row['id']" />
                    <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>

    <div data-reported>{{ $reported ?? '' }}</div>
</div>
