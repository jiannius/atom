{{-- title-case: a Title Case label. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Customer Billing Details" /> {{-- expect: title-case --}}
    <atom:page.header title="Customer billing details" />
    <atom:page.header title="Big Customer Report" :facts="[['label' => 'Due Date Range', 'value' => $x]]" /> {{-- expect: title-case --}}
    <atom:button>Invoice Acme Inc.</atom:button>
    <atom:page.header title="Customer Invoicing" :facts="[['label' => 'Due Date Range', 'value' => $x]]" /> {{-- expect: title-case --}}
    <atom:tabs :tabs="[['label' => 'Odd Items', 'href' => '#'], ['label' => 'Overdue Items List', 'href' => '#']]" /> {{-- expect: title-case --}}

    <atom:menu.item>Save As Template</atom:menu.item> {{-- expect: title-case --}}
    <atom:menu.item>Save as template</atom:menu.item>
    <atom:button>Use Template</atom:button> {{-- expect: title-case --}}
    <atom:button>{{ t('Delete Selected Record') }}</atom:button> {{-- expect: title-case --}}
    <atom:button>@lang('Send Reminder Email')</atom:button> {{-- expect: title-case --}}
    <atom:button>Template</atom:button>
    <atom:button>Send invoices</atom:button>
    <atom:button>Pay with Stripe</atom:button>
    <atom:button>Export to MyInvois</atom:button>
    <atom:button>Sign in with Google</atom:button>
    <atom:button>Invoices for March</atom:button>
    <atom:button>Due on Friday</atom:button>
    <atom:button>Export "Annual Report"</atom:button>
    <atom:button>Send (Draft)</atom:button>
    <atom:button>Show: Open orders</atom:button>
    <atom:button>Open orders. Then Close</atom:button>
    <atom:button>Download PDF</atom:button>
    <atom:button>Pay SST</atom:button>
    <atom:button>{{ $label }}</atom:button>
    <atom:button>Add Item {{ $n }}</atom:button>
    <atom:button icon="add" />

    <atom:input label="Phone Number" /> {{-- expect: title-case --}}
    <atom:input label="Phone number" />
    <atom:input label="Tax ID" />
    <atom:input label="SST number" />
    <atom:input label="Ship to Malaysia" />
    <atom:input.tel label="Mobile Phone Number" /> {{-- expect: title-case --}}
    <atom:select label="Payment Terms Applied" /> {{-- expect: title-case --}}
    <atom:select label="Payment terms">
        <atom:select.option value="nsw" label="New South Wales" />
    </atom:select>
    <atom:input :label="$label" />
    <atom:input :label="t('Billing Address Line')" /> {{-- expect: title-case --}}

    <atom:card heading="Recent Activity"> {{-- expect: title-case --}}
        <atom:heading>Open Invoices</atom:heading> {{-- expect: title-case --}}
        <atom:heading>Sales: Q3 report</atom:heading>
        <atom:heading level="2">Q3 report</atom:heading>
    </atom:card>
    <atom:panel heading="Last Month Totals" /> {{-- expect: title-case --}}
    <atom:form.section heading="Delivery Address Details" /> {{-- expect: title-case --}}
    <atom:empty heading="No Invoices Yet" /> {{-- expect: title-case --}}
    <atom:callout heading="Payment Overdue Notice" /> {{-- expect: title-case --}}
    <atom:card heading="Recent activity" />
    <atom:card :heading="$title" />

    <atom:table>
        <x-slot:columns>
            <atom:table.column>Due Date</atom:table.column> {{-- expect: title-case --}}
            <atom:table.column>Due date</atom:table.column>
            <atom:table.column numeric>Amount</atom:table.column>
        </x-slot:columns>
    </atom:table>

    <atom:tabs :tabs="[['label' => 'Open Orders', 'href' => '#'], ['label' => 'Paid', 'href' => '#'], ['label' => 'Past due', 'href' => '#']]" /> {{-- expect: title-case --}}
    <atom:page.header title="Job" :facts="[['label' => 'Delivery Date', 'value' => $date]]" /> {{-- expect: title-case --}}
</atom:page>

{{-- a hint in a slot is not part of the label ("search" is lowercase elsewhere, so a read "Ctrl K Search" would be flagged) --}}
<atom:button>Clear search</atom:button>
<atom:menu>
    <atom:menu.item icon="search">
        <x-slot:end>Ctrl K</x-slot:end>
        Search
    </atom:menu.item>
</atom:menu>
