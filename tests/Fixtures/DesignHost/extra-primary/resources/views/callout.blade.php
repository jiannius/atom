{{-- extra-primary: a callout's button is not a primary when the header has one (the two are in separate blocks, so they are not paired as a second primary on the screen). --}}
<atom:page>
    <atom:page.header title="Job">
        <x-slot:primary>
            @if ($ready)
                <atom:button variant="primary">Start</atom:button>
            @endif
        </x-slot:primary>
    </atom:page.header>

    @if ($blocked)
        <atom:callout variant="warning" heading="Waiting for parts">
            <atom:button variant="primary">Order the parts</atom:button> {{-- expect: extra-primary --}}
            <atom:button>Move the due date</atom:button>
        </atom:callout>
    @endif
</atom:page>
