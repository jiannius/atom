<div>
    <button type="button" wire:click="show('full')" data-show="full">Full</button>
    <button type="button" wire:click="show('blank')" data-show="blank">Blank</button>
    <button type="button" wire:click="show('mixed')" data-show="mixed">Mixed</button>

    <atom:table :empty="false" id="people" data-testid="people">
        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Email</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($rows as $row)
                <atom:table.row data-row="{{ $row['id'] }}" :wire:key="$keyed ? 'row-'.$row['id'] : null">
                    <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                    @if ($filler)
                        <atom:table.cell filler="n/a">{{ $row['email'] }}</atom:table.cell>
                    @else
                        <atom:table.cell>{{ $row['email'] }}</atom:table.cell>
                    @endif
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
