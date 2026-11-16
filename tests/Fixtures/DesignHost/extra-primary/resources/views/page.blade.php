{{-- extra-primary: a second primary on a screen. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:actions>
            <atom:button>Import</atom:button>
        </x-slot:actions>

        <x-slot:primary>
            <atom:button variant="primary">Create job</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:panel>
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button variant="ghost">Print</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>
    </atom:panel>

    <atom:button variant="accent">Also primary</atom:button> {{-- expect: extra-primary --}}

    {{-- not primaries --}}
    <atom:button variant="default" type="submit">A second submit, made plain</atom:button>
    <atom:button variant="ghost">Ghost</atom:button>
    <atom:button :variant="$tone">Bound variant</atom:button>
    <atom:button :type="$type">Bound type</atom:button>
    <atom:button type="submit" :variant="$tone">Bound variant on a submit</atom:button>
    <atom:button type="button">Plain</atom:button>
    <atom:menu.item variant="primary">Not a button</atom:menu.item>

    <!-- <atom:button variant="primary">In an HTML comment</atom:button> -->
    {{-- <atom:button variant="primary">In a Blade comment</atom:button> --}}
</atom:page>
