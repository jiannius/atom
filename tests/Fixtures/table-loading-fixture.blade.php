<div>
    <atom:table :paginate="$items" trashed>
        <x-slot:columns>
            <atom:table.column sort="name">Name</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($items as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
