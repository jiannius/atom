<div>
    <atom:table :rows="$orders">
        <atom:table.column>Name</atom:table.column>
    </atom:table>
    <atom:button type="submit">Save</atom:button>
    <atom:button type="submit" icon="check">Save</atom:button>
    <atom:button type="delete">Remove</atom:button>
    <atom:button variant="outline" size="sm">Back</atom:button>
    <atom:button variant="google">Google</atom:button>
    <atom:button variant="primary">Go</atom:button>
    <atom:badge color="amber">Pending</atom:badge>
    <atom:badge color="success">Done</atom:badge>
    <atom:badge color="#ff0000">Hex</atom:badge>
    <atom:chart color="teal" />
    <atom:chart color="#0f766e" />
    <atom:link href="/x" class="text-sky-600">Link</atom:link>
    <atom:navlist.group heading="Sales" class="space-y-1">x</atom:navlist.group>
    <atom:navlist.group>x</atom:navlist.group>
    <x-slot:dropdown>
        <atom:menu>
            <atom:menu.item>Profile</atom:menu.item>
        </atom:menu>
    </x-slot:dropdown>
    @include('partials.extra')
    {{-- <atom:editor> in a Blade comment is not a use --}}
    <p>{{ $order->placed_at }}</p>
    <p>{{ $order->placed_at->format('d M') }}</p>
</div>
