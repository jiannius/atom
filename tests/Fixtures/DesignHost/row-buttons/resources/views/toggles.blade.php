{{-- row-buttons: a plain button that a screen reader alone can name is a toggle; one with visible text is an action. --}}
<atom:table>
    <x-slot:rows>
        <atom:table.row>
            <atom:table.cell><button type="button" x-on:click="open = ! open"><span class="sr-only">Expand</span><atom:icon.chevron-down /></button></atom:table.cell>
            <atom:table.cell><button type="button" class="underline" wire:click="show">{{ __('View') }}</button></atom:table.cell> {{-- expect: row-buttons --}}
            <atom:table.cell><button type="button" wire:click="more"><span class="sr-only">More</span> Details</button></atom:table.cell> {{-- expect: row-buttons --}}
        </atom:table.row>
    </x-slot:rows>
</atom:table>
