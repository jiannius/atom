{{-- hand-settings-nav: a selector into the navlist's internals, a gutter override written into a class, or the plain grid that hand-rail-grid leaves alone (a fixed first track whose first child is a navigation). A line marked `expect:` is a finding (`x2`: two tokens on it); every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Settings" />

    <div class="grid md:grid-cols-[13rem_1fr]"> {{-- expect: hand-settings-nav --}}
        <atom:navlist class="max-md:flex-row max-md:[&_[data-atom-navlist-group-label]]:hidden"> {{-- expect: hand-settings-nav --}}
            <atom:navlist.item>Profile</atom:navlist.item>
        </atom:navlist>

        <div class="[&_[data-atom-navlist-item]]:w-auto [&>[data-atom-navlist]]:flex-row">Both</div> {{-- expect: hand-settings-nav x2 --}}
    </div>

    {{-- the plain grid, whatever the side menu is: a <nav>, an atom:tabs, a wrapper one level in --}}
    <div class="grid gap-8 md:grid-cols-[13rem_minmax(0,1fr)]"><nav><a href="#">Profile</a></nav><div>Main</div></div> {{-- expect: hand-settings-nav --}}
    <div class="grid gap-8 md:grid-cols-[13rem_minmax(0,1fr)]"><atom:tabs><atom:tab>Profile</atom:tab></atom:tabs><div>Main</div></div> {{-- expect: hand-settings-nav --}}
    <div class="grid gap-8 md:grid-cols-[13rem_minmax(0,1fr)]"><aside><nav><a href="#">Profile</a></nav></aside><div>Main</div></div> {{-- expect: hand-settings-nav --}}
    <div class="grid grid-cols-[14rem_1fr]"><div><atom:navlist><atom:navlist.item>Profile</atom:navlist.item></atom:navlist></div><div>Main</div></div> {{-- expect: hand-settings-nav --}}

    <div class="[--atom-gutter-s:0px]">Start</div> {{-- expect: hand-settings-nav --}}

    <div class="lg:[--atom-gutter-s:1rem] lg:[--atom-gutter-e:1rem]">Both gutters</div> {{-- expect: hand-settings-nav x2 --}}

    <atom:page class="[--atom-gutter-s:0px]!">Important</atom:page> {{-- expect: hand-settings-nav --}}

    <div :class="wide ? '[&_[data-atom-navlist-group-label]]:hidden' : ''">Alpine</div> {{-- expect: hand-settings-nav --}}

    {{-- look-alikes: none of these is flagged --}}
    <atom:navlist aria-label="Settings sections">
        <atom:navlist.group heading="Account">
            <atom:navlist.item href="/profile" class="font-semibold">Profile</atom:navlist.item>
        </atom:navlist.group>
    </atom:navlist>
    <div class="[--atom-page-pb:80px] [--atom-page-max:1100px]">Other variables of the page are the host's to set</div>
    <div class="[&_[data-atom-table]]:w-full">Another component's internals are not this check's</div>
    <div class="gutter-s atom-gutter [--gutter:1px]">Look-alike words</div>
    <div class="grid gap-8 md:grid-cols-[13rem_minmax(0,1fr)]"><div>Menu</div><div>Main</div></div>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"><div>Main</div><nav>Index</nav></div>
    <div class="grid grid-cols-[8rem_1fr]"><nav>A column of figures, not a side menu</nav><div>Main</div></div>
    <atom:card heading="Totals">
        <div class="grid md:grid-cols-[13rem_1fr]"><nav><a href="#">Inside a card</a></nav><div>Main</div></div>
    </atom:card>
    <p>A <code>[data-atom-navlist-item]</code> selector or a [--atom-gutter-s:0px] class in a sentence is only text.</p>
    <div style="--atom-gutter-s: 0px">A style attribute is not a class</div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="[--atom-gutter-s:0px]">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
