<div class="max-w-4xl">
    <atom:form x-on:submit.prevent>
        <atom:form.section heading="Customer" subheading="Who you are billing. Their tax ID goes on the invoice.">
            <atom:input label="Name" value="Customer A Sdn Bhd"/>
            <atom:input label="Tax ID" value="C0000000000"/>
        </atom:form.section>

        <atom:form.section heading="Details" subheading="Dates and terms printed on the invoice.">
            <atom:input label="Issue date" value="15 Oct 2026"/>
            <atom:input label="Payment terms" value="Net 30"/>
            <div class="@md:col-span-2"><atom:textarea label="Note to customer" rows="2"/></div>
        </atom:form.section>
    </atom:form>
</div>
