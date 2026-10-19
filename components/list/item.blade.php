@props([
    'href' => null,
    'rel' => 'noopener noreferrer nofollow',
    'newtab' => false,
])

@php
$href = safe_url($href);
$el = $href ? 'a' : 'div';
$sortable = $attributes->has('x-sort:item');
$clickable = $attributes->wire('click')->value() || $attributes->has('x-on:click') || $href;
$removeable = $attributes->get('wire:remove') || $attributes->get('x-on:remove');
@endphp

<div
class="group/list-item py-1.5 pr-1 flex rounded-control transition-colors hover:bg-fg/[0.04]"
{{ $attributes->only(['wire:remove', 'x-on:remove', 'x-sort:item']) }}
data-atom-list-item>
    @if ($sortable)
        <div x-sort:handle class="shrink-0 w-8 h-6 flex items-center justify-center text-fg-subtle cursor-move">
            <atom:icon.sort-handle class="size-4" />
        </div>
    @endif

    <{{ $el }}
    {{ $attributes->class([
        'grow first:pl-3 last:pr-3',
        'cursor-pointer' => $clickable,
    ])->merge([
        'href' => $href,
        'rel' => $href ? $rel : null,
        'target' => $href && $newtab ? '_blank' : null,
    ]) }}>
        {{ $slot }}
    </{{ $el }}>

    @if ($removeable)
        <button type="button" x-on:click.stop="$dispatch('remove')" aria-label="{{ t('Remove') }}" class="shrink-0 size-4 text-fg-muted transition-colors hover:text-fg flex items-center justify-center cursor-pointer py-3 ml-1 mr-2">
            <atom:icon.delete class="size-4" />
        </button>
    @endif
</div>
