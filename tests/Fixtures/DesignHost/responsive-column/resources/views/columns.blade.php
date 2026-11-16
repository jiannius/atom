{{-- responsive-column: a column hidden by hand at a breakpoint. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Parts" />

    <atom:panel>
        <atom:table frameless>
            <x-slot:columns>
                <atom:table.column name="code">Code</atom:table.column>
                <atom:table.column name="supplier" class="hidden lg:table-cell">Supplier</atom:table.column> {{-- expect: responsive-column --}}
                <atom:table.column name="bin" class="text-xs hidden md:table-cell">Bin</atom:table.column> {{-- expect: responsive-column --}}
                <atom:table.column name="note" class="max-lg:hidden">Note</atom:table.column> {{-- expect: responsive-column --}}
                <atom:table.column name="owner" class="dark:max-lg:hidden">Owner</atom:table.column> {{-- expect: responsive-column --}}
                <atom:table.column name="team" class="max-2xl:hidden">Team</atom:table.column> {{-- expect: responsive-column --}}
                <atom:table.column name="region" hideable class="hidden md:table-cell">Region</atom:table.column>
                <atom:table.column name="area" class="xmax-lg:hidden max-lg:hidden-x">Area</atom:table.column>
                <atom:table.column name="zone" class="hidden lg:table-cellx xlg:table-cell">Zone</atom:table.column>
                <atom:table.column name="stock" hideable class="max-sm:hidden">Stock</atom:table.column>
                <atom:table.column name="price" class="hidden sm:table-cell">Price</atom:table.column>
                <atom:table.column name="age" class="md:hidden">Age</atom:table.column>
                <atom:table.column name="cost" class="lg:table-cell">Cost</atom:table.column>
                <atom:table.column name="tag" class="{{ $width }} hidden-lg:table-cell">Tag</atom:table.column>
                <atom:table.column name="rack" :class="$wide ? 'hidden lg:table-cell' : ''">Rack</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell column="code">P-100</atom:table.cell>
                    <atom:table.cell column="supplier" class="hidden xl:table-cell">Acme</atom:table.cell> {{-- expect: responsive-column --}}
                    <atom:table.cell column="note" class="max-md:hidden">None</atom:table.cell> {{-- expect: responsive-column --}}
                    <atom:table.cell column="stock" class="max-sm:hidden">4</atom:table.cell>
                    <atom:table.cell column="price" numeric>9.00</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <table>
            <tr><td class="hidden lg:table-cell">A plain table is not atom's</td></tr>
        </table>
    </atom:panel>
</atom:page>
