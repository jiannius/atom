@props([
    'sticky' => false,
])

<div {{ $attributes->class([
    'flex items-center gap-3 justify-between',
    'sticky bottom-0 z-1 bg-white dark:bg-zinc-900 pt-4 -mb-2' => $sticky,
]) }} data-atom-form-actions>
    @if ($slot->isEmpty())
        <atom:button type="submit">{{ t('Save') }}</atom:button>
    @else
        {{ $slot }}
    @endif
</div>
