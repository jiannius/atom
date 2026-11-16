{{-- A list page built to the design rules: the header's one primary, a panel holding the toolbar and the table, the menu slots for the rest. --}}
<atom:page>
    <atom:page.header title="Invoices" :facts="['Acme Studio']">
        <x-slot:actions>
            <atom:button icon="upload">Import</atom:button>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="download">Export all</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="warning" confirm>Void unpaid</atom:menu.item>
        </x-slot:menu>

        <x-slot:primary>
            <atom:button variant="primary" icon="add">Create invoice</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:panel>
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search number or customer" />

            <atom:table.filters chips="inline">
                <atom:select variant="filter" add wire:model="filters.customer" label="Customer" :options="$customers" />
            </atom:table.filters>

            <x-slot:actions>
                <atom:button variant="ghost" icon="download">Export</atom:button>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="settings">Columns</atom:menu.item>
            </x-slot:menu>
        </atom:table.toolbar>

        <atom:table frameless :paginate="$invoices">
            <x-slot:checked>
                <atom:button size="sm">Send reminders</atom:button>
            </x-slot:checked>

            <x-slot:checked-menu>
                <atom:menu.item variant="delete">Delete</atom:menu.item>
            </x-slot:checked-menu>

            <x-slot:columns>
                <atom:table.column name="number">Number</atom:table.column>
                <atom:table.column name="status">Status</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($invoices as $invoice)
                    <atom:table.row :href="route('invoices.show', $invoice)">
                        <atom:table.cell column="number">{{ $invoice->number }}</atom:table.cell>
                        <atom:table.cell column="status"><atom:status :value="$invoice->status" /></atom:table.cell>
                        <atom:table.actions>
                            <atom:menu.item icon="eye">View</atom:menu.item>
                            <atom:menu.item variant="delete">Delete</atom:menu.item>
                        </atom:table.actions>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>

            <x-slot:empty>
                <atom:empty heading="No invoices yet">
                    <x-slot:action>
                        <atom:button>Import invoices</atom:button>
                    </x-slot:action>
                </atom:empty>
            </x-slot:empty>
        </atom:table>
    </atom:panel>
</atom:page>
