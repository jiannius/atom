{{-- Placeholder page content for the two shell patterns. <main> is unpadded: <atom:page> pads the page. --}}
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
$colors = ['Paid' => 'green', 'Overdue' => 'red', 'Sent' => 'blue', 'Draft' => null];
@endphp

<atom:page data-testid="pattern-page">
    <atom:page.header :title="$title">
        <x-slot:meta>
            <span><b class="font-medium text-fg">4</b> invoices</span>
            <span><b class="font-medium text-fg">1</b> overdue</span>
        </x-slot:meta>
    </atom:page.header>

    {{-- A table with no Livewire component behind it: static rows, no sorting or pagination. In a real page the rows come
         from a component (see the table docs). --}}
    <atom:table :empty="false">
        <x-slot:columns>
            <atom:table.column>Number</atom:table.column>
            <atom:table.column>Customer</atom:table.column>
            <atom:table.column>Status</atom:table.column>
            <atom:table.column align="right">Amount</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($rows as [$number, $customer, $status, $amount])
                <atom:table.row>
                    <atom:table.cell><span class="font-id">{{ $number }}</span></atom:table.cell>
                    <atom:table.cell class="font-medium">{{ $customer }}</atom:table.cell>
                    <atom:table.cell><atom:badge size="xs" :color="$colors[$status]">{{ $status }}</atom:badge></atom:table.cell>
                    <atom:table.cell align="right" class="tabular-nums">{{ $amount }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</atom:page>
