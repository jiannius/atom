<atom:page.header
title="Work order WO-1042"
:crumbs="[['label' => 'Jobs', 'href' => '#'], ['label' => 'WO-1042']]"
:facts="[
    ['label' => 'Customer', 'value' => 'Customer A'],
    ['label' => 'Site', 'value' => 'Unit 7, Harbour Park'],
    ['label' => 'Due', 'value' => '29 Jul'],
    ['label' => 'Ref', 'value' => 'PO-88213', 'mono' => true],
]">
    <x-slot:status>
        <atom:status tone="info" running label="In progress"/>
    </x-slot:status>

    <x-slot:actions>
        <atom:button>Edit</atom:button>
        <atom:button icon="download">PDF</atom:button>
    </x-slot:actions>

    <x-slot:menu>
        <atom:menu.item icon="copy">Duplicate</atom:menu.item>
        <atom:menu.item icon="delete">Cancel job</atom:menu.item>
    </x-slot:menu>

    <x-slot:primary>
        <atom:button variant="primary">Mark as done</atom:button>
    </x-slot:primary>
</atom:page.header>
