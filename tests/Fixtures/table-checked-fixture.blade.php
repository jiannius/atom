<div>
    <div @class(['overflow-hidden rounded-panel border bg-surface' => $frameless]) data-testid="panel">
        <atom:table :frameless="$frameless" :sticky-selection="$sticky" select-all :paginate="$this->pager">
            <x-slot:checked :tone="$tone">
                <atom:button size="sm" data-testid="bulk-action">Send reminders</atom:button>
            </x-slot:checked>

            <x-slot:columns>
                <atom:table.column checkbox />
                <atom:table.column>Name</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach (\Jiannius\Atom\Tests\Fixtures\TableCheckedFixture::ROWS as $row)
                    <atom:table.row wire:key="row-{{ $row['id'] }}">
                        <atom:table.cell :checkbox="$row['id']" />
                        <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </div>
</div>
