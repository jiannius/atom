{{-- row-buttons: a table whose rows edit something is a form of lines: its buttons are controls, not row actions. Nothing here is flagged. --}}
<atom:page>
    <atom:page.header title="Invoice" />

    <atom:form wire:submit="save">
        <atom:table>
            <x-slot:rows>
                @foreach ($lines as $i => $line)
                    <atom:table.row>
                        <atom:table.cell><atom:input wire:model="lines.{{ $i }}.name" aria-label="Name" /></atom:table.cell>
                        <atom:table.cell><atom:button variant="ghost" icon="delete" wire:click="remove({{ $i }})" aria-label="Remove line" /></atom:table.cell>
                    </atom:table.row>
                @endforeach

                <atom:table.row>
                    <atom:table.cell colspan="2"><atom:button variant="ghost" icon="add" wire:click="addLine">Add line</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:form>
</atom:page>
