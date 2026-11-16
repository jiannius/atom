{{-- page-header (b): a hand-built page title. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <div class="flex justify-between">
        <h1>Invoices</h1> {{-- expect: page-header --}}
        <atom:button variant="primary">Create invoice</atom:button>
    </div>

    <atom:panel heading="Invoices">
        <atom:heading size="sm">Overdue</atom:heading>
        <h1>A heading in a panel is not the page's title</h1>
    </atom:panel>
</atom:page>

<atom:page>
    <header>
        <atom:heading size="xl">Jobs</atom:heading> {{-- expect: page-header --}}
    </header>
</atom:page>

<atom:page>
    <main>
        <h1>Main</h1> {{-- expect: page-header --}}
    </main>
</atom:page>

<atom:page>
    <section>
        <atom:heading size="lg" level="1">Customers</atom:heading> {{-- expect: page-header --}}
        <atom:heading size="20">Suppliers</atom:heading> {{-- expect: page-header --}}
    </section>
</atom:page>

{{-- the headings below are not titles, so the page has none: it is the page that is told --}}
{{-- expect: page-header --}}
<atom:page>
    <div class="flex">
        <atom:heading size="17">Not as big as a title</atom:heading>
        <atom:heading size="sm">Small</atom:heading>
    </div>
    <div class="fixed inset-0"><atom:heading size="xl">Overlay</atom:heading></div>
    <div class="text-center"><atom:heading size="xl">An empty state</atom:heading></div>
</atom:page>

<h1>Outside a page</h1>
