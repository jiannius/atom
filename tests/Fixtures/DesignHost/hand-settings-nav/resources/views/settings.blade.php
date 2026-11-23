{{-- hand-settings-nav: a selector into the navlist's internals, or a gutter override, written into a class. A line marked `expect:` is a finding (`x2`: two tokens on it); every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Settings" />

    <div class="grid md:grid-cols-[13rem_1fr]">
        <atom:navlist class="max-md:flex-row max-md:[&_[data-atom-navlist-group-label]]:hidden"> {{-- expect: hand-settings-nav --}}
            <atom:navlist.item>Profile</atom:navlist.item>
        </atom:navlist>

        <div class="[&_[data-atom-navlist-item]]:w-auto [&>[data-atom-navlist]]:flex-row">Both</div> {{-- expect: hand-settings-nav x2 --}}
    </div>

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
    <p>A <code>[data-atom-navlist-item]</code> selector or a [--atom-gutter-s:0px] class in a sentence is only text.</p>
    <div style="--atom-gutter-s: 0px">A style attribute is not a class</div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="[--atom-gutter-s:0px]">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
