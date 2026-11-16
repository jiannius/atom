{{-- confirm-copy: a confirm dialog whose copy does not name the action. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Member">
        <x-slot:menu>
            <atom:confirm.trigger heading="Archive?" x-on:confirmed="$wire.archive()"> {{-- expect: confirm-copy --}}
                <atom:menu.item variant="warning">Archive</atom:menu.item>
            </atom:confirm.trigger>

            <atom:confirm.trigger heading="Archive?" button-confirm="Yes" x-on:confirmed="$wire.archive()"> {{-- expect: confirm-copy --}}
                <atom:menu.item variant="warning">Archive</atom:menu.item>
            </atom:confirm.trigger>

            <atom:confirm.trigger heading="Leave?" buttonConfirm=" Okay. " x-on:confirmed="$wire.leave()"> {{-- expect: confirm-copy --}}
                <atom:link href="#">Leave</atom:link>
            </atom:confirm.trigger>

            <atom:confirm.trigger heading="Void invoice?" message="This CANNOT be undone" button-confirm="Void" x-on:confirmed="$wire.void()"> {{-- expect: confirm-copy --}}
                <atom:menu.item variant="warning">Void</atom:menu.item>
            </atom:confirm.trigger>

            <atom:menu.item variant="warning" :confirm="['buttonConfirm' => 'Yes']" wire:click="void">Void invoice</atom:menu.item> {{-- expect: confirm-copy --}}
            <atom:menu.item variant="warning" :confirm="['heading' => 'Void invoice?', 'buttonConfirm' => t('Proceed')]" wire:click="void">Void invoice</atom:menu.item> {{-- expect: confirm-copy --}}
            <atom:menu.item variant="warning" :confirm="['heading' => 'Void THIS invoice?']" wire:click="void">Void invoice</atom:menu.item> {{-- expect: confirm-copy --}}
            <atom:menu.item variant="warning" :confirm="['message' => ['It leaves every list.', 'NEVER undo it.']]" wire:click="void">Void invoice</atom:menu.item> {{-- expect: confirm-copy --}}
            <atom:menu.item variant="warning" confirm="Void THIS invoice?" wire:click="void">Void invoice</atom:menu.item> {{-- expect: confirm-copy --}}
        </x-slot:menu>

        <x-slot:actions>
            <atom:button x-on:click="atom.confirm({ heading: 'Void invoice?' })">Void</atom:button> {{-- expect: confirm-copy --}}
            <atom:button x-on:click="atom.confirm({ heading: 'Void invoice?', buttonConfirm: 'Continue' })">Void</atom:button> {{-- expect: confirm-copy --}}
            <atom:button x-on:click="atom.confirm({ heading: 'Void invoice?', message: 'It stays in IMPORTANT lists', buttonConfirm: 'Void' })">Void</atom:button> {{-- expect: confirm-copy --}}
        </x-slot:actions>
    </atom:page.header>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:menu.item variant="warning" confirm>Void invoice</atom:menu.item>
    <atom:menu.item variant="warning" confirm="Void this invoice?">Void invoice</atom:menu.item>
    <atom:menu.item variant="warning" :confirm="['heading' => 'Void?', 'message' => 'It stays in the list.']">Void invoice</atom:menu.item>
    <atom:menu.item variant="warning" :confirm="['heading' => 'Void?', 'buttonConfirm' => 'Void invoice']">Void invoice</atom:menu.item>
    <atom:button variant="danger" inverted :confirm="['message' => 'Resets the SMTP settings and the HTML templates']">Reset mail</atom:button>
    <atom:confirm.trigger heading="Leave?" message="You stop getting its messages." button-confirm="Leave" x-on:confirmed="$wire.leave()">
        <atom:link href="#">Leave</atom:link>
    </atom:confirm.trigger>
    <atom:confirm.trigger heading="Leave?" button-confirm="Leave this conversation" x-on:confirmed="$wire.leave()">
        <atom:link href="#">Leave</atom:link>
    </atom:confirm.trigger>
    <atom:confirm.trigger {{ $attributes }} heading="Leave?">
        <atom:link href="#">Leave</atom:link>
    </atom:confirm.trigger>
    <atom:confirm.trigger :button-confirm="$verb" heading="Leave?">
        <atom:link href="#">Leave</atom:link>
    </atom:confirm.trigger>
    <atom:button x-on:click="atom.confirm({ heading: 'Void invoice?', buttonConfirm: 'Void' })">Void</atom:button>
    <atom:button x-on:click="atom.confirm(options)">Void</atom:button>
    <atom:button x-on:click="atom.confirm({ ...options })">Void</atom:button>
    <atom:button x-on:click="atom.notify({ heading: 'Void invoice?' })">Void</atom:button>
    <atom:button x-on:click="window.confirm('Void?')">Void</atom:button>
    <atom:button wire:click="void">Void</atom:button>
    <atom:button variant="danger" inverted :confirm="['message' => 'LHDN and KWSP both get the NRIC']">Void</atom:button>
    <script>
        // atom.confirm({ heading: 'Void invoice?' })
        /* atom.confirm({ heading: 'Void invoice?', buttonConfirm: 'Yes' }) */
        const url = 'https://example.test/path'; atom.confirm({ heading: 'Void invoice?', buttonConfirm: 'Void' })
    </script>

    <!-- <atom:confirm.trigger heading="Archive?"><atom:button>Archive</atom:button></atom:confirm.trigger> -->
    {{-- <atom:confirm.trigger heading="Archive?"><atom:button>Archive</atom:button></atom:confirm.trigger> --}}
</atom:page>
