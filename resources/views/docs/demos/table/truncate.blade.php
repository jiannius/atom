<div class="max-w-xl">
    <atom:table :empty="false">
        <x-slot:columns>
            <atom:table.column>Customer</atom:table.column>
            <atom:table.column>Address</atom:table.column>
            <atom:table.column align="right">Amount</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>Jane Cooper</atom:table.cell>
                <atom:table.cell truncate muted>Level 12, Menara Example, Jalan Sultan Ismail, 50250 Kuala Lumpur, Wilayah Persekutuan</atom:table.cell>
                <atom:table.cell align="right">RM 1,250.00</atom:table.cell>
            </atom:table.row>

            {{-- wraps onto several lines instead of staying on one --}}
            <atom:table.row>
                <atom:table.cell>Wade Warren</atom:table.cell>
                <atom:table.cell class="whitespace-normal" muted>Level 12, Menara Example, Jalan Sultan Ismail, 50250 Kuala Lumpur, Wilayah Persekutuan</atom:table.cell>
                <atom:table.cell align="right">RM 890.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>
