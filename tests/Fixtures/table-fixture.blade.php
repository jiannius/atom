<div>
    <atom:table :paginate="$this->items">
        <x-slot:columns>
            <atom:table.column sort="name">Name</atom:table.column>
            <atom:table.column sort="amount" align="right">Amount</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            @foreach ($this->items as $item)
                <atom:table.row>
                    <atom:table.cell>{{ $item->name }}</atom:table.cell>
                    <atom:table.cell align="right">{{ $item->amount }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
