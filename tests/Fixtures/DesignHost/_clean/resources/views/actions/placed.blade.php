{{-- The same rules, written as `<atom:action>`s: each surface places its own, so there is no cap, order, separator or tone to get wrong. A primary, a frequent action by status, a utility, a warning and a delete in the header; a toolbar; a bulk bar with its delete; rows; a card; and one host button beside the actions that still fits. --}}
<atom:page>
    <atom:page.header title="Invoices">
        <x-slot:primary>
            @if ($draft)
                <atom:action wire:click="send">Send invoice</atom:action>
            @else
                <atom:action wire:click="close">Close invoice</atom:action>
            @endif
        </x-slot:primary>

        <x-slot:actions>
            <atom:action icon="edit" :href="route('invoices.edit', $invoice)" wire:navigate>Edit</atom:action>

            @if ($invoice->isOverdue())
                <atom:action icon="send" wire:click="remind">Send reminder</atom:action>
            @endif

            <atom:action icon="copy" priority="low" wire:click="duplicate">Duplicate</atom:action>
            <atom:action tone="warning" wire:click="void">Void invoice</atom:action>
            <atom:action tone="delete" wire:click="destroy">Delete invoice</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:panel heading="Lines">
        <x-slot:actions>
            <atom:action wire:click="addLine">Add line</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action wire:click="importLines">Import lines</atom:action>
            <atom:action tone="warning" wire:click="clearLines">Clear lines</atom:action>
        </x-slot:menu>

        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search lines" />

            <x-slot:actions>
                <atom:button variant="ghost" icon="download">Export</atom:button>
                <atom:action wire:click="print">Print</atom:action>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="settings">Columns</atom:menu.item>
                <atom:action tone="warning" wire:click="resetOrder">Reset order</atom:action>
            </x-slot:menu>
        </atom:table.toolbar>

        <atom:table frameless>
            <x-slot:checked>
                <atom:action wire:click="assign">Assign</atom:action>
                <atom:action wire:click="schedule">Schedule</atom:action>
                <atom:action wire:click="exportSelected">Export selected</atom:action>
                <atom:action tone="delete" wire:click="destroyMany">Delete</atom:action>
            </x-slot:checked>

            <x-slot:checked-menu>
                <atom:action wire:click="tag">Tag selected</atom:action>
            </x-slot:checked-menu>

            <x-slot:columns>
                <atom:table.column>Line</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($lines as $line)
                    <atom:table.row :href="route('lines.show', $line)">
                        <atom:table.cell>{{ $line->name }}</atom:table.cell>

                        <atom:table.cell>
                            <atom:table.actions>
                                <atom:action wire:click="duplicateLine({{ $line->id }})">Duplicate</atom:action>
                                <atom:action tone="delete" wire:click="destroyLine({{ $line->id }})">Delete</atom:action>
                            </atom:table.actions>
                        </atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:action :href="route('customers.edit', $customer)" wire:navigate>Edit</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action wire:click="copyEmail">Copy email</atom:action>
            <atom:action tone="warning" wire:click="suspend">Suspend customer</atom:action>
        </x-slot:menu>
    </atom:card>
</atom:page>
