{{-- a page with a header: only a level 1 is a hand title beside it; a bigger heading is a section's --}}
<atom:page>
    <atom:page.header title="Work order">
        <x-slot:meta>
            <h1>Anything inside the header is the header's</h1>
        </x-slot:meta>
    </atom:page.header>

    <div class="rounded-panel border bg-surface p-4">
        <h1>A hand card holds its own heading</h1>
    </div>

    <div class="shadow-sm"><h1>Shadow</h1></div>
    <div class="ring-1"><h1>Ring</h1></div>
    <div class="bg-surface"><h1>Surface</h1></div>
    <section class="bg-white p-4"><h1>White</h1></section>
    <div class="border p-4"><h1>Bordered</h1></div>
    <div class="flex items-center border-b pb-4"><h1>A header row has a rule under it</h1></div> {{-- expect: page-header --}}
    <div class="rounded-lg"><h1>Rounded</h1></div>

    <div class="flex items-center">
        {{-- expect: page-header --}}
        <atom:heading
            level="1"
        >Work order</atom:heading>
    </div>

    <main>
        <h1>Main</h1> {{-- expect: page-header --}}
    </main>

    <hgroup>
        <h1>Group</h1> {{-- expect: page-header --}}
    </hgroup>

    <atom:heading size="xl">A section title, with a header in the page</atom:heading>
    <atom:heading size="lg" level="2">Recent activity</atom:heading>
    <section class="border-t pt-4"><atom:heading size="xl" level="3">Activity</atom:heading></section>

    <atom:card heading="Customer">
        <h1>A card's title</h1>
        <atom:heading level="1">A card's heading</atom:heading>
    </atom:card>

    <aside>
        <h1>An aside is not the page</h1>
    </aside>

    <ul>
        <li><h1>A list item</h1></li>
    </ul>

    <atom:modal name="note">
        <h1>A modal's title</h1>
    </atom:modal>

    <atom:heading :size="$size">Bound</atom:heading>
    <atom:heading>Default</atom:heading>
    <!-- <h1>Commented out</h1> -->
    {{-- <h1>Commented out</h1> --}}
</atom:page>

<h1>Outside a page</h1>
<atom:heading level="1">Outside a page</atom:heading>
