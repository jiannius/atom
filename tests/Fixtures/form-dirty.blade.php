<div>
    <atom:page data-testid="page">
        <button type="button" wire:click="touch" data-touch>Touch</button>
        <span>Touches: <span data-touches>{{ $touches }}</span> Saves: <span data-saves>{{ $saves }}</span></span>

        <atom:form wire:submit="save" data-form="main" :error-toast="false">
            <atom:input label="Name" wire:model="name" data-probe="name" />
            <atom:input label="Note" wire:model.live="note" data-probe="note" />
            <atom:select label="Region" variant="listbox" wire:model="region" data-select="region" :options="[
                ['value' => 'north', 'label' => 'North'],
                ['value' => 'south', 'label' => 'South'],
            ]" />
            <atom:select label="Zone" wire:model="zone" data-probe="zone" :options="[
                ['value' => 'a', 'label' => 'Zone A'],
                ['value' => 'b', 'label' => 'Zone B'],
            ]" />
            <atom:date-picker label="Due" wire:model="due" data-probe="due" />

            <div class="h-[800px]">Long content</div>

            @if ($dirty)
                <atom:form.actions bar dirty>
                    <x-slot:start><span>Total <b>RM 10.00</b></span></x-slot:start>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @else
                <atom:form.actions bar>
                    <x-slot:start><span>Total <b>RM 10.00</b></span></x-slot:start>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @endif
        </atom:form>
    </atom:page>
</div>
