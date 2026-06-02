<atom:table :empty="false">
    <x-slot:columns>
        <atom:table.column>Customer</atom:table.column>
        <atom:table.column>Email</atom:table.column>
        <atom:table.column align="right">Amount</atom:table.column>
    </x-slot:columns>

    <x-slot:rows>
        <atom:table.row>
            <atom:table.cell>Jane Cooper</atom:table.cell>
            <atom:table.cell muted>jane@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 1,250.00</atom:table.cell>
        </atom:table.row>

        <atom:table.row>
            <atom:table.cell>Wade Warren</atom:table.cell>
            <atom:table.cell muted>wade@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 890.00</atom:table.cell>
        </atom:table.row>

        <atom:table.row>
            <atom:table.cell>Esther Howard</atom:table.cell>
            <atom:table.cell muted>esther@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 2,400.00</atom:table.cell>
        </atom:table.row>
    </x-slot:rows>
</atom:table>
