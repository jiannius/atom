@props([
    'sticky' => false,
])

{{-- The sticky bar bleeds out into the modal's own p-6 padding on all three sides it
     touches. A sticky element is constrained to its scroll container's CONTENT box, so
     `bottom-0` parks it a padding's height above the panel edge and a strip of scrolling
     content stays visible underneath — the buttons then read as half cut off. `-bottom-6`
     moves the stop down to the panel edge, `pb-6` refills what that would have exposed
     (so the buttons stay put), `-mb-6` absorbs the padding once the scroll reaches the
     end, and -mx-6/px-6 lets the background and the divider span the full width. --}}
<div {{ $attributes->class([
    'flex items-center gap-3 justify-between',
    'sticky -bottom-6 z-1 bg-white dark:bg-zinc-900 -mx-6 px-6 pt-4 pb-6 -mb-6' => $sticky,
    'border-t border-zinc-100 dark:border-zinc-700' => $sticky,
]) }} data-atom-form-actions>
    @if ($slot->isEmpty())
        <atom:button type="submit">{{ t('Save') }}</atom:button>
    @else
        {{ $slot }}
    @endif
</div>
