{{-- The footer slot is a muted, wrapping row under a hairline, in tabular figures: a count of what the list shows, or a totals line. --}}
<atom:panel class="max-w-md" heading="Parts used">
    <atom:table frameless :empty="false">
        <x-slot:columns>
            <atom:table.column>Part</atom:table.column>
            <atom:table.column numeric>Cost (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>Filter</atom:table.cell>
                <atom:table.cell numeric>120.00</atom:table.cell>
            </atom:table.row>

            <atom:table.row>
                <atom:table.cell>Belt</atom:table.cell>
                <atom:table.cell numeric>300.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <x-slot:footer>
        <span>2 of 8 parts</span>
        <span class="ml-auto">RM 420.00</span>
    </x-slot:footer>
</atom:panel>
