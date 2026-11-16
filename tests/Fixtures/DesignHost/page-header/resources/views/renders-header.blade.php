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

<atom:page>
    <div><x-job-header :job="$job" /></div>
    <atom:callout variant="warning">Read only</atom:callout>
</atom:page>

<atom:page>
    <div>@include('jobs.header')</div>
    <atom:callout variant="warning">Read only</atom:callout>
</atom:page>

<atom:page>
    <header>{{ $header }}</header>
    <atom:callout variant="warning">Read only</atom:callout>
</atom:page>

<atom:page>
    <div class="flex"><livewire:jobs.header :job="$job" /></div>
    <atom:callout variant="warning">Read only</atom:callout>
</atom:page>

<atom:page>
    <div class="mx-auto">{{ $slot }}</div>
</atom:page>

<atom:page>
    @yield('content')
    <div>Rows</div>
</atom:page>

<atom:page>
    @stack('styles')
    <div>Rows</div>
</atom:page>

<atom:page>
    @each('jobs.row', $jobs, 'job')
    <div>Rows</div>
</atom:page>

<atom:page>
    @component('jobs.header')@endcomponent
    <div>Rows</div>
</atom:page>

<atom:page>
    @embed('jobs.header')@endembed
    <div>Rows</div>
</atom:page>

<atom:page>
    {!! $header !!}
    <div>Rows</div>
</atom:page>

<atom:page>
    <x-slot:aside>Aside</x-slot:aside>
    <div>Rows</div>
</atom:page>

<atom:page>
    <div class="space-y-6">
        <x-job-header :job="$job" />
        <atom:card>Details</atom:card>
    </div>
</atom:page>
