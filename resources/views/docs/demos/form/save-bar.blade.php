{{-- The bar bleeds to the gutter of the page it sits in (<atom:page>); this box stands in for one --}}
<div class="relative h-72 overflow-y-auto rounded-panel border px-4 pt-4" style="--atom-gutter-s: 1rem; --atom-gutter-e: 1rem; --atom-page-pb: 0px">
    <atom:form x-on:submit.prevent>
        <atom:form.section heading="Items" subheading="Amounts in Malaysian Ringgit.">
            <atom:input label="Item one" value="Base module"/>
            <atom:input label="Item two" value="Bookings"/>
            <atom:input label="Item three" value="Reports"/>
            <atom:input label="Item four" value="Data migration"/>
        </atom:form.section>

        <atom:form.actions bar>
            <x-slot:start>
                <span class="text-fg-subtle">Subtotal <b class="ms-1.5 font-medium text-fg">4,140.00</b></span>
                <span class="text-[17px] font-semibold text-fg">RM 4,231.20</span>
            </x-slot:start>

            <atom:button type="button" variant="ghost">Cancel</atom:button>
            <atom:button type="submit">Save and send</atom:button>
        </atom:form.actions>
    </atom:form>
</div>
