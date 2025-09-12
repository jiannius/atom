@props([
    'href' => null,
    'rel' => 'noopener noreferrer nofollow',
    'newtab' => false,
])

@php
$el = $href ? 'a' : 'div';
$sortable = $attributes->has('x-sort:item');
$clickable = $attributes->wire('click')->value() || $attributes->has('x-on:click') || $href;
$removeable = $attributes->get('wire:remove') || $attributes->get('x-on:remove');
@endphp

<div
class="group/list-item py-2 pr-1 flex rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700"
{{ $attributes->only(['wire:remove', 'x-on:remove', 'x-sort:item']) }}
data-atom-list-item>
    @if ($sortable)
        <div x-sort:handle class="shrink-0 w-8 h-6 flex items-center justify-center text-muted-more cursor-move">
            <atom:icon.sort-handle />
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
        <div x-on:click.stop="$dispatch('remove')" class="shrink-0 size-4 text-muted-foreground flex items-center justify-center cursor-pointer py-3 ml-1 mr-2">
            <atom:icon.delete />
        </div>
    @endif
</div>
