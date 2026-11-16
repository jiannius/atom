{{-- success-button: a button in the success or info tone. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:primary>
            <atom:button variant="primary">Schedule</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:button variant="ghost" color="success">Mark as inspected</atom:button> {{-- expect: success-button --}}
    <atom:button variant="success">Approve</atom:button> {{-- expect: success-button --}}
    <atom:button variant="info" size="sm">Details</atom:button> {{-- expect: success-button --}}
    <atom:button color="info">Notes</atom:button> {{-- expect: success-button --}}
    <x-atom::button variant="success">Approve</x-atom::button> {{-- expect: success-button --}}

    {{-- expect: success-button --}}
    <atom:button
        class="mt-2"
        variant="success"
    >Mark as paid</atom:button>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:callout variant="success">Saved</atom:callout>
    <atom:callout variant="info">Paid on 3 May</atom:callout>
    <atom:badge color="success">Paid</atom:badge>
    <atom:button variant="primary">Approve</atom:button>
    <atom:button variant="ghost" color="danger" type="button">Archive</atom:button>
    <atom:button.group variant="success">
        <atom:button>One</atom:button>
    </atom:button.group>
    <atom:button :variant="$tone">Bound</atom:button>
    <atom:button variant="{{ $tone }}">Echoed</atom:button>
    <!-- <atom:button variant="success">Commented out</atom:button> -->
    {{-- <atom:button variant="success">Commented out</atom:button> --}}
    @verbatim
        <atom:button variant="success">Documentation</atom:button>
    @endverbatim
</atom:page>
