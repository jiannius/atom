<div>
    <output data-testid="log" class="block">{{ json_encode($log) }}</output>
    <output data-testid="renders" class="block">{{ $renders }}</output>

    <atom:table :empty="false">
        <x-slot:header>
            <atom:table.toolbar>
                <atom:table.search />

                <x-slot:actions>
                    <atom:action icon="download" wire:click="record('export')" data-testid="export">Export</atom:action>
                    <atom:action icon="send" wire:click="record('import')" data-testid="import">Import</atom:action>
                    <atom:action wire:click="record('archive')" data-testid="archive">Archive old</atom:action>
                </x-slot:actions>

                <x-slot:menu>
                    <atom:action priority="low" wire:click="record('print')" data-testid="print">Print list</atom:action>
                    <atom:action tone="warning" wire:click="record('void')" data-testid="void-all">Void all</atom:action>
                </x-slot:menu>
            </atom:table.toolbar>
        </x-slot:header>

        <x-slot:checked :tone="$tone">
            @if ($reminder)
                <atom:action wire:click="record('remind')" data-testid="remind">Send reminder</atom:action>
            @endif
            <atom:action wire:click="record('assign')" data-testid="assign">Assign</atom:action>
            <atom:action wire:click="record('tag')" data-testid="tag">Tag</atom:action>
            <atom:action wire:click="record('move')" data-testid="move">Move</atom:action>
            <atom:action wire:click="record('merge')" data-testid="merge">Merge</atom:action>
            <atom:action tone="warning" wire:click="record('void')" data-testid="void">Void</atom:action>
            <atom:action tone="delete" data-testid="delete">Delete</atom:action>
        </x-slot:checked>

        <x-slot:checked-menu>
            <atom:action wire:click="record('copy')" data-testid="copy">Copy ids</atom:action>
        </x-slot:checked-menu>

        <x-slot:columns>
            <atom:table.column checkbox />
            <atom:table.column>Name</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach (\Jiannius\Atom\Tests\Fixtures\ActionTableFixture::ROWS as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell :checkbox="$row['id']" />
                    <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>

    <button type="button" wire:click="bump" data-testid="bump" class="mt-6">Re-render</button>
    <button type="button" wire:click="addReminder" data-testid="add-reminder">Add the reminder action</button>
</div>
