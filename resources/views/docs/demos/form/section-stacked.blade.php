<div class="max-w-xl">
    <atom:form x-on:submit.prevent>
        <atom:form.section layout="stacked" heading="Contact" subheading="Who we write to about invoices.">
            <atom:input label="Name"/>
            <atom:input label="Email" type="email"/>
        </atom:form.section>

        <atom:form.section layout="stacked" heading="Billing" subheading="Printed on every invoice.">
            <atom:input label="Company"/>
            <atom:input label="Tax ID"/>
        </atom:form.section>
    </atom:form>
</div>
