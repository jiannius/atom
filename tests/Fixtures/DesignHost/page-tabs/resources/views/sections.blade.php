{{-- page-tabs. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Work order" />

    @if ($job->hasSections())
        <atom:tabs :tabs="$tabs" wire:model="tab" /> {{-- expect: page-tabs --}}
    @endif

    <atom:page.tabs :tabs="$sections" />

    <atom:panel heading="Lines">
        <atom:tabs variant="button" :tabs="$views" wire:model="view" />
    </atom:panel>

    <atom:card heading="Notes">
        <atom:tabs :tabs="$kinds" />
    </atom:card>

    <div class="max-w-lg">
        <atom:tabs :tabs="$wrapped" />
    </div>

    <atom:page.columns>
        <atom:tabs :tabs="$columns" />
    </atom:page.columns>
</atom:page>

<atom:tabs :tabs="$outside" />
