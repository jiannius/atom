{{-- A record page built to the design rules: status, facts, one primary and the menu in the header; the sections are cards and a panel. --}}
<atom:page>
    <atom:page.header :title="$invoice->number" :crumbs="[['label' => 'Invoices', 'href' => route('invoices.index')]]">
        <x-slot:status>
            <atom:status :value="$invoice->status" />
        </x-slot:status>

        <x-slot:actions>
            <atom:button icon="download">Download PDF</atom:button>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="copy">Duplicate</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="warning" confirm>Void invoice</atom:menu.item>
            <atom:menu.item variant="delete">Delete</atom:menu.item>
        </x-slot:menu>

        <x-slot:primary>
            <atom:button variant="primary">Send to customer</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:page.columns>
        <atom:panel heading="Lines" :count="$invoice->lines->count()">
            <atom:table frameless>
                <x-slot:columns>
                    <atom:table.column>Description</atom:table.column>
                    <atom:table.column numeric>Amount</atom:table.column>
                </x-slot:columns>
                <x-slot:rows>
                    @foreach ($invoice->lines as $line)
                        <atom:table.row>
                            <atom:table.cell>{{ $line->description }}</atom:table.cell>
                            <atom:table.cell numeric>{{ $line->amount }}</atom:table.cell>
                        </atom:table.row>
                    @endforeach
                </x-slot:rows>
            </atom:table>
        </atom:panel>

        <x-slot:rail label="Details">
            <atom:card heading="Customer">
                <x-slot:actions>
                    <atom:link href="#">View all</atom:link>
                </x-slot:actions>

                <x-slot:menu>
                    <atom:menu.item icon="edit">Change customer</atom:menu.item>
                </x-slot:menu>

                <atom:dd label="Name">{{ $invoice->customer->name }}</atom:dd>
            </atom:card>
        </x-slot:rail>
    </atom:page.columns>
</atom:page>
