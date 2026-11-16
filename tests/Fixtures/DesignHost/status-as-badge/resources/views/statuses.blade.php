{{-- status-as-badge: a workflow state drawn as a badge. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Invoice 1042">
        <x-slot:status>
            <atom:badge color="success">Paid</atom:badge> {{-- expect: status-as-badge --}}
        </x-slot:status>
    </atom:page.header>

    <atom:page.header title="Invoice 1043">
        <x-slot:status>
            <atom:status :value="$invoice->status" />
        </x-slot:status>

        <x-slot:actions>
            <atom:badge>Draft copy</atom:badge>
        </x-slot:actions>
    </atom:page.header>

    <x-layouts.card>
        <x-slot:status>
            <atom:badge color="info">Not the header's</atom:badge>
        </x-slot:status>
    </x-layouts.card>

    <atom:badge :status="$invoice->status" /> {{-- expect: status-as-badge --}}
    <atom:badge :status="$invoice['state']" /> {{-- expect: status-as-badge --}}
    <atom:badge :status="$stage" /> {{-- expect: status-as-badge --}}
    <atom:badge :status="$invoice?->status()" /> {{-- expect: status-as-badge --}}
    <atom:badge :status="$client->tier" />
    <atom:badge :status="$product->type" />
    <atom:badge :status="$order->statusLabel" />
    <atom:badge color="neutral">3</atom:badge>

    <atom:table>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="status"><atom:badge color="info">Open</atom:badge></atom:table.cell> {{-- expect: status-as-badge --}}
                <atom:table.cell column="state">
                    <atom:badge color="warning">Hold</atom:badge> {{-- expect: status-as-badge --}}
                </atom:table.cell>
                <atom:table.cell column="type"><atom:badge color="info">Retail</atom:badge></atom:table.cell>
                <atom:table.cell column="status"><atom:status :value="$row->status" /></atom:table.cell>
                <atom:table.cell><atom:badge color="info">No column</atom:badge></atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</atom:page>
