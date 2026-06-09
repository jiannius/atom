@props([
    'label' => 'Show archived',
])

<atom:button
    variant="ghost"
    size="sm"
    wire:click="$toggle('_table.show_trashed')"
    x-bind:class="$wire._table.show_trashed && 'bg-zinc-100 dark:bg-zinc-700'"
    {{ $attributes }}
    data-atom-table-trashed>
    <atom:icon.archive class="size-4" />
    {{ t($label) }}
</atom:button>
