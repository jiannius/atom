{{-- page-block-margin. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Work order" />

    <div class="mt-6"> {{-- expect: page-block-margin --}}
        <atom:callout variant="warning">Read only</atom:callout>
    </div>

    <atom:panel heading="Lines" class="mb-4">Rows</atom:panel> {{-- expect: page-block-margin --}}

    <div class="flex -mt-2 gap-2">Pulled up</div> {{-- expect: page-block-margin --}}

    <div class="lg:my-4">A variant</div> {{-- expect: page-block-margin --}}

    <div class="m-4">All round</div> {{-- expect: page-block-margin --}}

    <atom:page.columns>
        <div class="mb-6">Main</div> {{-- expect: page-block-margin --}}

        <x-slot:rail label="Details" class="mt-6">
            <atom:card heading="Customer" class="mt-6">Name</atom:card> {{-- expect: page-block-margin --}}

            <atom:card heading="People">
                <div class="mt-2">Deeper in a card</div>
            </atom:card>
        </x-slot:rail>
    </atom:page.columns>

    {{-- the look-alikes --}}
    <div class="mt-0">Zero adds no room</div>

    <div class="my-auto">Auto adds none</div>

    <div class="mx-auto max-w-lg">Centred</div>

    <div class="ms-auto me-4 px-4 py-2">Horizontal</div>

    <div class="flex justify-end gap-2">
        <span class="ms-auto">In a footer</span>
        <span class="mt-6">Deeper inside a block</span>
    </div>

    <atom:panel heading="Lines">
        <div class="mt-4">Deeper inside a panel</div>
    </atom:panel>

    <div class="space-y-4">Spacing inside a block is the block's</div>

    <div class="mt-{{ $gap }}">An echoed value</div>
</atom:page>

<atom:page class="space-y-6"> {{-- expect: page-block-margin --}}
    <atom:page.header title="Work order" />
</atom:page>

<atom:page class="mt-4 max-w-xl">
    <atom:page.header title="Work order" />
</atom:page>

<div class="mt-6">Outside a page</div>
