{{-- action-misplaced: a surface takes the action out and leaves a client condition or a wrapper behind, empty. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:actions>
            <div x-show="open">
                <atom:action wire:click="a">Import</atom:action> {{-- expect: action-misplaced --}}
            </div>

            <template x-if="open">
                <atom:action wire:click="b">Export</atom:action> {{-- expect: action-misplaced --}}
            </template>

            <span wire:show="open"><atom:action wire:click="c">Print</atom:action></span> {{-- expect: action-misplaced --}}

            <template x-for="tool in tools">
                <atom:action wire:click="d">Tool</atom:action> {{-- expect: action-misplaced --}}
            </template>
        </x-slot:actions>

        <x-slot:menu>
            <atom:tooltip text="Copy">
                <atom:action wire:click="e">Copy link</atom:action> {{-- expect: action-misplaced --}}
            </atom:tooltip>

            <atom:confirm.trigger heading="Archive?" button-confirm="Archive">
                <atom:action tone="warning" wire:click="f">Archive</atom:action> {{-- expect: action-misplaced --}}
            </atom:confirm.trigger>
        </x-slot:menu>
    </atom:page.header>

    <atom:table.toolbar>
        <x-slot:actions>
            <atom:dropdown>
                <atom:action wire:click="g">Export</atom:action> {{-- expect: action-misplaced --}}
            </atom:dropdown>

            <atom:button.group>
                <atom:action wire:click="h">Day</atom:action> {{-- expect: action-misplaced --}}
            </atom:button.group>
        </x-slot:actions>
    </atom:table.toolbar>
</atom:page>
