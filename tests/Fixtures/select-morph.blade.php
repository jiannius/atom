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
</div>
