@props([
    'label' => 'Show archived',
])

<atom:tooltip :content="$label">
    <atom:button
        icon="archive"
        variant="ghost"
        :aria-label="t($label)"
        wire:click="$toggle('_table.show_trashed')"
        x-bind:aria-pressed="$wire._table.show_trashed ? 'true' : 'false'"
        x-bind:class="$wire._table.show_trashed && 'bg-zinc-800 text-white hover:bg-zinc-800 hover:text-white dark:bg-white dark:text-zinc-800 dark:hover:bg-white dark:hover:text-zinc-800'"
        {{ $attributes }}
        data-atom-table-trashed />
</atom:tooltip>
