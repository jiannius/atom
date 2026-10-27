{{-- The bar is a card in the form's flow, as wide as the sections above it; this scrolling box stands in for the page --}}
<div class="relative h-72 overflow-y-auto rounded-panel border px-4 pt-4">
    <atom:form x-on:submit.prevent>
        <atom:form.section heading="Items" subheading="Amounts in Malaysian Ringgit.">
            <atom:input label="Item one" value="Base module"/>
            <atom:input label="Item two" value="Bookings"/>
            <atom:input label="Item three" value="Reports"/>
            <atom:input label="Item four" value="Data migration"/>
        </atom:form.section>

        <atom:form.actions bar>
            <x-slot:start>
                <span>Subtotal <b>4,140.00</b></span>
                <span>Total <b>RM 4,231.20</b></span>
            </x-slot:start>

            <atom:button type="button" variant="ghost">Cancel</atom:button>
            <atom:button type="submit">Save and send</atom:button>
        </atom:form.actions>
    </atom:form>
</div>
