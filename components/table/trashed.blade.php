@props([
    'label' => 'Show archived',
])

<atom:tooltip :content="$label">
    <atom:button
        icon="archive"
        variant="ghost"
        :aria-label="t($label)"
        wire:click="$toggle('_table.show_trashed')"
        x-bind:class="$wire._table.show_trashed && 'bg-zinc-100 dark:bg-zinc-700'"
        {{ $attributes }}
        data-atom-table-trashed />
</atom:tooltip>
