{{-- a page that opens with something that may draw the header: none of these is flagged --}}
<atom:page>
    @include('invoices.header')
    <atom:callout variant="warning">Read only</atom:callout>
</atom:page>

<atom:page>
    @livewire('invoices.header')
    <atom:panel heading="Lines">Rows</atom:panel>
</atom:page>

<atom:page>
    <livewire:invoices.header :invoice="$invoice" />
</atom:page>

<atom:page>
    <x-invoices.header :invoice="$invoice" />
    <atom:panel heading="Lines">Rows</atom:panel>
</atom:page>

<atom:page>
    {{ $slot }}
</atom:page>

<atom:page>
    {{ $header }}
    <div>Rows</div>
</atom:page>

<atom:page />

<atom:page>
</atom:page>
