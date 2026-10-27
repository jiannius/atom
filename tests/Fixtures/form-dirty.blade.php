<div>
    <atom:page data-testid="page">
        <button type="button" wire:click="touch" data-touch>Touch</button>
        <span>Touches: <span data-touches>{{ $touches }}</span> Saves: <span data-saves>{{ $saves }}</span> Drafts: <span data-drafts>{{ $drafts }}</span></span>

        <atom:form wire:submit="save" data-form="main" :error-toast="false" x-data="{ form: { total: 5 }, dirty: 'host', keys: 'host-keys' }">
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

            {{-- a control with no wire:model: only its own events can say it changed --}}
            <atom:input label="Free" data-probe="free" />

            {{-- an editor that syncs on blur: a focus and a blur with no change is not a change --}}
            <atom:tiptap label="Body" wire:model.blur="body" data-probe="body" />

            {{-- a repeater: a button adds a row, another removes one --}}
            @foreach ($rows as $index => $row)
                <atom:input label="Row {{ $index + 1 }}" wire:model="rows.{{ $index }}.label" wire:key="row-{{ $index }}" data-probe="row-{{ $index }}" />
            @endforeach
            <div class="flex gap-2">
                <button type="button" wire:click="addRow" data-add-row>Add row</button>
                <button type="button" wire:click="removeRow" data-remove-row>Remove row</button>
                <button type="button" x-on:click="atom.modal('inner').show()" data-open-inner>Open inner</button>
            </div>

            {{-- a child Livewire component inside the form, with a wire:model field of its own --}}
            <livewire:atom-e2e-form-dirty-child />

            {{-- a modal that sits inside the form: what is typed in it is not the form's --}}
            <atom:modal name="inner" class="w-[24rem]">
                <input type="text" data-probe="inner" aria-label="Inner">
            </atom:modal>

            <div class="h-[800px]">Long content</div>

            @if ($dirty)
                <atom:form.actions bar dirty>
                    <x-slot:start><span>Total <b>RM 10.00</b> <i data-testid="host-scope" x-text="form.total + ':' + dirty + ':' + keys"></i></span></x-slot:start>
                    <atom:button type="button" wire:click="saveDraft" data-save-draft>Save draft</atom:button>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @else
                <atom:form.actions bar>
                    <x-slot:start><span>Total <b>RM 10.00</b> <i data-testid="host-scope" x-text="form.total + ':' + dirty + ':' + keys"></i></span></x-slot:start>
                    <atom:button type="button" wire:click="saveDraft" data-save-draft>Save draft</atom:button>
                    <atom:button type="submit" data-submit="main">Save</atom:button>
                </atom:form.actions>
            @endif
        </atom:form>
    </atom:page>
</div>
