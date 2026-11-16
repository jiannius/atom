{{-- extra-primary: a host component decides if its slot is on the page, so what is in it is not read; a layout is the page. --}}
<x-layouts.app>
    <atom:page>
        <atom:page.header title="Job">
            <x-slot:primary>
                <atom:button variant="primary">Start</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <atom:form wire:submit="save">
            <atom:button type="submit">Save</atom:button>
        </atom:form>

        {{-- not read: it may not draw its slot --}}
        <x-confirm-modal name="go">
            <atom:button variant="primary" wire:click="go">Go</atom:button>
        </x-confirm-modal>

        <atom:button variant="primary" wire:click="again">Again</atom:button> {{-- expect: extra-primary --}}
    </atom:page>
</x-layouts.app>
