{{-- confirm-tone: a confirm wrapper whose dialog is not in the element's tone. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Invoice">
        <x-slot:menu>
            <atom:confirm.trigger heading="Void invoice?" button-confirm="Void" x-on:confirmed="$wire.void()"> {{-- expect: confirm-tone --}}
                <atom:menu.item variant="warning">Void invoice</atom:menu.item>
            </atom:confirm.trigger>

            <atom:confirm.trigger variant="warning" heading="Delete invoice?" button-confirm="Delete" x-on:confirmed="$wire.delete()"> {{-- expect: confirm-tone --}}
                <atom:menu.item variant="delete">Delete invoice</atom:menu.item>
            </atom:confirm.trigger>

            <atom:confirm.trigger variant="danger" heading="Void invoice?" button-confirm="Void" x-on:confirmed="$wire.void()"> {{-- expect: confirm-tone --}}
                <atom:button variant="warning" inverted>Void invoice</atom:button>
            </atom:confirm.trigger>

 {{-- the dialog has no error tone: it draws the accent one --}}
            <atom:confirm.trigger variant="error" heading="Purge?" button-confirm="Purge" x-on:confirmed="$wire.purge()"> {{-- expect: confirm-tone --}}
                <atom:button variant="danger" inverted>Purge</atom:button>
            </atom:confirm.trigger>

            <atom:confirm.trigger variant="success" heading="Void invoice?" button-confirm="Void" x-on:confirmed="$wire.void()"> {{-- expect: confirm-tone --}}
                <atom:button variant="ghost" color="warning">Void invoice</atom:button>
            </atom:confirm.trigger>

            {{-- expect: confirm-tone --}}
            <atom:confirm.trigger
                heading="Purge invoice?"
                button-confirm="Purge"
                x-on:confirmed="$wire.purge()"
            >
                <atom:button type="delete">Purge</atom:button>
            </atom:confirm.trigger>
        </x-slot:menu>
    </atom:page.header>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:confirm.trigger variant="warning" heading="Void invoice?" button-confirm="Void" x-on:confirmed="$wire.void()">
        <atom:menu.item variant="warning">Void invoice</atom:menu.item>
    </atom:confirm.trigger>

    <atom:confirm.trigger variant="danger" heading="Delete invoice?" button-confirm="Delete" x-on:confirmed="$wire.delete()">
        <atom:button type="delete">Delete invoice</atom:button>
    </atom:confirm.trigger>

    <atom:confirm.trigger variant="danger" heading="Remove line?" button-confirm="Remove" x-on:confirmed="$wire.remove()">
        <atom:menu.item variant="remove">Remove line</atom:menu.item>
    </atom:confirm.trigger>

    <atom:confirm.trigger variant="danger" heading="Purge?" button-confirm="Purge" x-on:confirmed="$wire.purge()">
        <atom:button variant="danger" inverted>Purge</atom:button>
    </atom:confirm.trigger>

    {{-- a plain element, a wrapper around something else, more than one element, or a tone that cannot be read --}}
    <atom:confirm.trigger heading="Send invoice?" button-confirm="Send" x-on:confirmed="$wire.send()">
        <atom:button>Send</atom:button>
    </atom:confirm.trigger>

    <atom:confirm.trigger variant="warning" heading="Send invoice?" button-confirm="Send" x-on:confirmed="$wire.send()">
        <atom:button>Send</atom:button>
    </atom:confirm.trigger>

    <atom:confirm.trigger heading="Leave?" button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <atom:link href="#">Leave</atom:link>
    </atom:confirm.trigger>

    <atom:confirm.trigger heading="Leave?" button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <a href="#"><atom:menu.item variant="warning">Leave</atom:menu.item></a>
    </atom:confirm.trigger>

    <atom:confirm.trigger heading="Leave?" button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <atom:menu.item variant="warning">Leave</atom:menu.item>
        <atom:menu.item variant="delete">Delete</atom:menu.item>
    </atom:confirm.trigger>

    <atom:confirm.trigger :variant="$tone" heading="Leave?" button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <atom:menu.item variant="warning">Leave</atom:menu.item>
    </atom:confirm.trigger>

    <atom:confirm.trigger heading="Leave?" button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <atom:menu.item :variant="$tone">Leave</atom:menu.item>
    </atom:confirm.trigger>

    <atom:menu.item variant="warning" confirm>Void invoice</atom:menu.item>

    <!-- <atom:confirm.trigger heading="Void?" button-confirm="Void"><atom:menu.item variant="warning">Void</atom:menu.item></atom:confirm.trigger> -->
</atom:page>
