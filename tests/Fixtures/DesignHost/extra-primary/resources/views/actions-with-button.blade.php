{{-- extra-primary: the header's primary action and a primary button on the same screen are two primaries. --}}
<atom:page>
    <atom:page.header title="Invoice">
        <x-slot:primary>
            <atom:action wire:click="send">Send</atom:action>
        </x-slot:primary>
    </atom:page.header>

    <atom:button variant="primary">Another primary</atom:button> {{-- expect: extra-primary --}}
</atom:page>
