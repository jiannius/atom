{{-- Placeholder page content for the two shell patterns. <main> is unpadded: <atom:page> pads the page. It follows the list page:
     the page's ONE primary action ("New invoice") is the header's `primary` slot, never the shell's `actions` slot (that one is for
     a secondary action or nothing); the table is a frameless one in a panel; a status is <atom:status>; money is `numeric` on the
     column and the cell, with the currency named once in the header ("Amount (RM)"). --}}
@php
$page = request()->query('page', 'invoices');
$page = is_string($page) ? $page : 'invoices';
$title = str($page)->headline();
$rows = [
    ['INV-0412', 'Customer A', 'Paid', '1,280.00'],
    ['INV-0411', 'Customer B', 'Overdue', '640.00'],
    ['INV-0410', 'Customer C', 'Sent', '2,115.50'],
    ['INV-0409', 'Customer D', 'Draft', '320.00'],
];
$tones = ['Paid' => 'success', 'Overdue' => 'danger', 'Sent' => 'info', 'Draft' => 'neutral'];
@endphp

<atom:page data-testid="pattern-page">
    <atom:page.header :title="$title">
        <x-slot:meta>
            <span><b class="font-medium text-fg">4</b> invoices</span>
            <span><b class="font-medium text-fg">1</b> overdue</span>
        </x-slot:meta>

        <x-slot:primary>
            <atom:button variant="primary" icon="add" data-testid="shell-primary">New invoice</atom:button>
        </x-slot:primary>
    </atom:page.header>

    {{-- A table with no Livewire component behind it: static rows, no sorting or pagination. In a real page the rows come
         from a component (see the table docs). --}}
    <div class="min-w-0 overflow-hidden rounded-panel border bg-surface">
        <atom:table frameless :empty="false">
            <x-slot:columns>
                <atom:table.column>Number</atom:table.column>
                <atom:table.column>Customer</atom:table.column>
                <atom:table.column>Status</atom:table.column>
                <atom:table.column numeric>Amount (RM)</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($rows as [$number, $customer, $status, $amount])
                    <atom:table.row>
                        <atom:table.cell><span class="font-id">{{ $number }}</span></atom:table.cell>
                        <atom:table.cell class="font-medium">{{ $customer }}</atom:table.cell>
                        <atom:table.cell><atom:status :tone="$tones[$status]" :label="$status"/></atom:table.cell>
                        <atom:table.cell numeric>{{ $amount }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </div>
</atom:page>
