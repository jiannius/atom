<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
    </div>

    <atom:select
    variant="listbox"
    label="Country"
    options="countries"
    searchable
    wire:model="country"
    data-select="callback" />

    <atom:select
    variant="listbox"
    label="Status"
    wire:model="status"
    data-select="static"
    :options="[
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]" />

    {{-- Mirrors a consuming app's form modal: mounted with the page, populated +
         opened by a Livewire action, so the action's re-render morphs into the
         picker inside it. --}}
    <atom:button wire:click="edit(7)" data-edit>Edit record</atom:button>

    <atom:modal name="form">
        {{-- the modal is a native dialog, so a re-render has to be triggered from
             inside it once it is open --}}
        <atom:button wire:click="bump" data-bump-modal>Bump</atom:button>

        <atom:select
        variant="listbox"
        label="Customer"
        options="countries"
        searchable
        wire:key="customer-{{ $record ?? 'new' }}"
        wire:model.live="customer"
        data-select="modal" />

        {{-- a short list, like the two-row picker in the report --}}
        <atom:select
        variant="listbox"
        label="Priority"
        wire:key="priority-{{ $record ?? 'new' }}"
        wire:model.live="priority"
        data-select="modal-static"
        :options="[
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'high', 'label' => 'High'],
        ]" />

        {{-- multiple renders its selected chips through x-for inside the trigger,
             the same Alpine-owned-DOM shape as the option rows --}}
        <atom:select
        variant="listbox"
        label="Tags"
        multiple
        wire:key="tags-{{ $record ?? 'new' }}"
        wire:model="tags"
        data-select="modal-multiple"
        :options="[
            ['value' => 'a', 'label' => 'Alpha'],
            ['value' => 'b', 'label' => 'Beta'],
            ['value' => 'c', 'label' => 'Gamma'],
        ]" />
    </atom:modal>
</div>
