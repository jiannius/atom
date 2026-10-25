<div>
    <atom:page data-testid="page">
        <div class="flex gap-3">
            <button type="button" wire:click="touch" data-touch>Touch</button>
            <button type="button" wire:click="toggleBar" data-toggle-bar>Toggle bar</button>
            <span>Touches: <span data-touches>{{ $touches }}</span></span>
        </div>

        <atom:form wire:submit="save" data-form="main">
            <atom:input label="Name" wire:model="name" data-probe="name" />

            {{-- a page taller than the screen, so the bar is pinned to the bottom of it --}}
            <div class="{{ $short ? 'h-[100px]' : 'h-[1400px]' }}">Long content. Saves: <span data-saves>{{ $saves }}</span></div>

            @if ($bar)
                <atom:form.actions bar>
                    <x-slot:start><span>Unsaved changes</span></x-slot:start>
                    <atom:button type="button">Cancel</atom:button>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @else
                <atom:form.actions>
                    <atom:button type="button">Cancel</atom:button>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @endif
        </atom:form>
    </atom:page>

    @if ($toastInside)
        <atom:toast />
    @endif
</div>
