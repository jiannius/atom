<div {{ $attributes->class([
    'size-4 rounded flex items-center justify-center cursor-pointer',
    'bg-surface border border-fg-subtle text-surface',
    '*:opacity-0',
    'data-[checked]:border-accent-ink data-[checked]:bg-accent-ink data-[checked]:*:opacity-100',
]) }} data-atom-table-checkbox>
    <atom:icon.check class="size-3" />
</div>
