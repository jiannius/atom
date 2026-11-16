{{-- a page with a header and a hand title beside it: the title is still a finding --}}
<atom:page>
    <atom:page.header title="Work order">
        <x-slot:meta>
            <h1>Anything inside the header is the header's</h1>
        </x-slot:meta>
    </atom:page.header>

    <div class="rounded-panel border bg-surface p-4">
        <atom:heading size="xl">A hand card holds its own heading</atom:heading>
    </div>

    <div class="flex items-center">
        {{-- expect: page-header --}}
        <atom:heading
            size="xl"
        >Work order</atom:heading>
    </div>

    <atom:card heading="Customer">
        <atom:heading size="xl">A card's heading</atom:heading>
    </atom:card>

    <aside>
        <atom:heading size="xl">An aside is not the page</atom:heading>
    </aside>

    <ul>
        <li><atom:heading size="lg">A list item</atom:heading></li>
    </ul>

    <atom:modal name="note">
        <h1>A modal's title</h1>
    </atom:modal>

    <atom:heading size="17">Not as big as a title</atom:heading>
    <atom:heading :size="$size">Bound</atom:heading>
    <atom:heading>Default</atom:heading>
    <!-- <h1>Commented out</h1> -->
    {{-- <h1>Commented out</h1> --}}
</atom:page>
