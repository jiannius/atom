<atom:page.header
title="Invoice INV-0042"
:crumbs="[['label' => 'Invoices', 'href' => '#'], ['label' => 'INV-0042']]"
:facts="[['label' => 'Customer', 'value' => 'Customer A'], ['label' => 'Due', 'value' => '29 Jul']]">
    <x-slot:status>
        <atom:status tone="warning" label="Overdue"/>
    </x-slot:status>

    <x-slot:actions>
        <atom:action priority="primary" x-on:click="atom.toast({ message: 'Invoice sent.', variant: 'success' })">Send invoice</atom:action>
        <atom:action icon="edit" href="#">Edit</atom:action>
        <atom:action icon="download" x-on:click="atom.toast({ message: 'PDF ready.', variant: 'success' })">Download PDF</atom:action>
        <atom:action icon="copy" priority="low" x-on:click="atom.toast({ message: 'Duplicated.', variant: 'success' })">Duplicate</atom:action>
        <atom:action tone="warning" x-on:click="atom.toast({ message: 'Voided.', variant: 'success' })">Void invoice</atom:action>
        <atom:action tone="delete" x-on:click="atom.toast({ message: 'Deleted.', variant: 'success' })">Delete invoice</atom:action>
    </x-slot:actions>
</atom:page.header>
