{{-- `rows-toggle-when-selected` (#235): a plain href row, a wire:navigate one, a wire:click one, controls inside, and a bulk bar for the hint. --}}
<div>
    <atom:table :rows-toggle-when-selected="! $off" :sticky-selection="$sticky">
        <x-slot:checked>
            <atom:button size="sm" data-testid="bulk-action">Send reminders</atom:button>
        </x-slot:checked>

        <x-slot:columns>
            <atom:table.column checkbox />
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Controls</atom:table.column>
            <atom:table.column />
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row href="/atom/e2e/table-row-toggle?go=plain" data-row="plain">
                <atom:table.cell checkbox="1" />
                <atom:table.cell>Plain row</atom:table.cell>
                <atom:table.cell>
                    <button type="button" data-testid="button" x-on:click="$el.dataset.clicked = '1'">Act</button>
                    <a href="/atom/e2e/table-row-toggle?go=link" data-testid="link">Link</a>
                    <span data-testid="plain-text">Text</span>
                </atom:table.cell>
                <atom:table.actions data-testid="actions-cell"><atom:menu.item>Edit</atom:menu.item></atom:table.actions>
            </atom:table.row>
            <atom:table.row wire:navigate href="/atom/e2e/table-row-toggle?go=spa" data-row="spa">
                <atom:table.cell checkbox="2" />
                <atom:table.cell>SPA row</atom:table.cell>
                <atom:table.cell />
                <atom:table.cell />
            </atom:table.row>
            <atom:table.row wire:click="ping" data-row="wire">
                <atom:table.cell checkbox="3" />
                <atom:table.cell>Wire row</atom:table.cell>
                <atom:table.cell />
                <atom:table.cell />
            </atom:table.row>
            <atom:table.row href="/atom/e2e/table-row-toggle?go=nocheck" data-row="nocheck">
                <atom:table.cell>No checkbox</atom:table.cell>
                <atom:table.cell>No checkbox row</atom:table.cell>
                <atom:table.cell />
                <atom:table.cell />
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <div data-testid="pings">{{ $pings }}</div>
</div>
