{{-- A row holds no visible button: every action in `<atom:table.actions>` is a row of its ⋯, untoned first, then the `low` ones, a separator,
     then warning, then danger and delete. A toned row asks before it acts, in its tone; a delete with no handler of its own calls `$wire.delete()`. --}}
<atom:table :empty="false">
    <x-slot:columns>
        <atom:table.column>Number</atom:table.column>
        <atom:table.column>Customer</atom:table.column>
        <atom:table.column></atom:table.column>
    </x-slot:columns>
    <x-slot:rows>
        <atom:table.row>
            <atom:table.cell>INV-1042</atom:table.cell>
            <atom:table.cell>Jane Cooper</atom:table.cell>
            <atom:table.actions>
                <atom:action icon="edit" x-on:click="atom.toast({ message: 'Editing.', variant: 'success' })">Edit</atom:action>
                <atom:action icon="send" x-on:click="atom.toast({ message: 'Sent.', variant: 'success' })">Send</atom:action>
                <atom:action icon="copy" priority="low" x-on:click="atom.toast({ message: 'Link copied.', variant: 'success' })">Copy link</atom:action>
                <atom:action tone="warning" x-on:click="atom.toast({ message: 'Voided.', variant: 'success' })">Void</atom:action>
                <atom:action tone="delete" x-on:click="atom.toast({ message: 'Deleted.', variant: 'success' })">Delete</atom:action>
            </atom:table.actions>
        </atom:table.row>
    </x-slot:rows>
</atom:table>
