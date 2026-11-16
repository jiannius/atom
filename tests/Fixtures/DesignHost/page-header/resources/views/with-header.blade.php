{{-- a page that opens with its header, and one that opens with something else but has a header further down: neither is flagged --}}
<atom:page>
    <atom:page.header title="Invoices" />
    <atom:panel heading="Lines">Rows</atom:panel>
</atom:page>

<atom:page>
    <atom:callout variant="warning">Read only</atom:callout>
    <atom:page.header title="Invoice" />
</atom:page>
