{{-- hand-card-title: a card or panel titled by hand. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:card> {{-- expect: hand-card-title --}}
        <h3 class="text-xs font-medium">Customer</h3>
        <p>Body</p>
    </atom:card>

    <atom:card> {{-- expect: hand-card-title --}}
        <atom:heading size="sm">Customer</atom:heading>
    </atom:card>

    <atom:card variant="default"> {{-- expect: hand-card-title --}}
        <h2>Customer</h2>
    </atom:card>

    <atom:panel> {{-- expect: hand-card-title --}}
        <div class="flex items-center justify-between">
            <h4>Lines</h4>
            <a href="#">View all</a>
        </div>
        <p>Body</p>
    </atom:panel>

    <atom:card> {{-- expect: hand-card-title --}}
        @if ($show)
            <h5>Shown sometimes</h5>
        @endif
    </atom:card>

    <atom:card> {{-- expect: hand-card-title --}}
        <x-slot:actions>
            <atom:link href="#">Edit</atom:link>
        </x-slot:actions>

        <h3>Customer</h3>
    </atom:card>

    {{-- look-alikes: none of these is flagged --}}
    <atom:card heading="Customer">
        <p>Body</p>
    </atom:card>

    <atom:card variant="stats" heading="Open">
        <h3>Not a title: a stats card prints its own</h3>
    </atom:card>

    <atom:card :variant="$variant">
        <h3>A bound variant is not read</h3>
    </atom:card>

    <atom:card>
        <x-slot:heading>Customer</x-slot:heading>
        <h3>A heading slot is the title; this is body</h3>
    </atom:card>

    <atom:card :heading="$title">
        <h3>A bound heading</h3>
    </atom:card>

    <atom:card {{ $attributes }}>
        <h3>An attribute bag could carry the heading</h3>
    </atom:card>

    <atom:card>
        <p>Text first</p>
        <h3>A title that follows other content</h3>
    </atom:card>

    <atom:card>
        {{ $intro }}
        <h3>An echo before it</h3>
    </atom:card>

    <atom:card>
        <h1>An h1 is the page title</h1>
    </atom:card>

    <atom:card>
        <div class="flex">
            <p>A div that does not begin with a heading</p>
            <h3>Later</h3>
        </div>
    </atom:card>

    <atom:card>
        <div>
            <div>
                <h3>Two divs down is out of reach</h3>
            </div>
        </div>
    </atom:card>

    <atom:card>
        <atom:subheading>A subheading is not a card title</atom:subheading>
    </atom:card>

    <div>
        <h3>A plain div is not a card</h3>
    </div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <atom:card>
                <h3>The host's own look</h3>
            </atom:card>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
