{{-- one-red: a visible destructive button is the tint. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Order">
        <x-slot:actions>
            <atom:button variant="danger">Delete</atom:button> {{-- expect: one-red --}}
            <atom:button variant="error" wire:click="purge">Purge</atom:button> {{-- expect: one-red --}}
            <atom:button variant="danger" type="submit">Close account</atom:button> {{-- expect: one-red --}}
            <atom:button variant="ghost" color="danger">Remove</atom:button> {{-- expect: one-red --}}
            <atom:button variant="ghost" color="error" icon="delete" /> {{-- expect: one-red --}}
            <atom:button variant="ghost" class="text-danger-fg">Remove</atom:button> {{-- expect: one-red --}}
            <atom:button variant="ghost" :class="['text-danger-fg' => $overdue]">Remove</atom:button> {{-- expect: one-red --}}
            {{-- expect: one-red --}}
            <atom:button
                variant="danger"
                wire:click="purge"
            >Purge</atom:button>
        </x-slot:actions>
    </atom:page.header>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:button type="delete">Delete</atom:button>
    <atom:button variant="danger" inverted>Purge</atom:button>
    <atom:button variant="danger" type="delete">Delete</atom:button>
    <atom:button variant="warning">Void</atom:button>
    <atom:button variant="ghost" color="warning">Void</atom:button>
    <atom:button variant="ghost" color="primary">View</atom:button>
    <atom:button variant="ghost" class="text-fg-muted">More</atom:button>
    <atom:button variant="ghost" class="px-2 hover:text-danger-fg">Remove</atom:button>
    <atom:button variant="ghost" class="text-danger-{{ $level }}">More</atom:button>
    <atom:button class="text-danger-fg">Not a ghost</atom:button>
    <atom:button variant="default" color="danger" class="text-danger-fg">Not a ghost</atom:button>
    <atom:button variant="link" color="danger" class="text-danger-fg">Not a ghost</atom:button>
    <atom:button :variant="$tone">Purge</atom:button>
    <atom:button variant="danger" :type="$type">Purge</atom:button>
    <atom:button variant="danger" :inverted="$soft">Purge</atom:button>
    <atom:link href="#" class="text-danger-fg">Delete</atom:link>

    {{-- a hand-built confirm dialog: the solid fill is its button's --}}
    <atom:modal name="purge">
        <atom:button variant="danger" wire:click="purge">Purge</atom:button>
    </atom:modal>

    <!-- <atom:button variant="danger">Delete</atom:button> -->
</atom:page>
