@props([
    'variant' => 'archived',   // archived | voided
    'label' => null,
    'icon' => null,
])

@php
$label ??= $variant === 'voided' ? 'Show voided' : 'Show archived';
$icon ??= $variant === 'voided' ? 'delete' : 'archive';
@endphp

<atom:tooltip :content="$label">
    <atom:button
        :icon="$icon"
        variant="ghost"
        :aria-label="t($label)"
        wire:click="$toggle('_table.show_trashed')"
        x-bind:aria-pressed="$wire._table.show_trashed ? 'true' : 'false'"
        x-bind:class="$wire._table.show_trashed && 'bg-fg! text-surface! hover:bg-fg! hover:text-surface!'"
        {{ $attributes }}
        data-atom-table-trashed />
</atom:tooltip>
