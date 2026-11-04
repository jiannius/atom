<div>
    <atom:table :paginate="$items">
        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
            <atom:table.column align="right">Amount</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            @foreach ($items as $item)
                <atom:table.row>
                    <atom:table.cell>{{ $item->name }}</atom:table.cell>
                    <atom:table.cell align="right">{{ $item->amount }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
