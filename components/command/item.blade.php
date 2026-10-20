@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
])

@php
$href = safe_url($href);
$el = $href ? 'a' : 'button';
$label = trim(strip_tags($slot->toHtml()));
$classes = Arr::toCssClasses([
    'flex w-full cursor-pointer items-center gap-2.5 rounded-nav px-2.5 min-h-control py-1 text-start text-sm',
    'text-fg focus-visible:-outline-offset-2',
    'hover:bg-fg/[0.06]',
    '[&[data-active]]:bg-fg/[0.06]',
]);
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge([
    'type' => $el === 'button' ? 'button' : false,
    'href' => $el === 'button' ? false : $href,
    'role' => 'option',
    'data-atom-command-item' => true,
    'data-label' => $label,
]) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0 text-fg-subtle"/>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($shortcut)
        <kbd class="shrink-0 rounded-nav border border-border-strong bg-surface-sunken px-1.5 text-xs font-medium text-fg-muted">{{ $shortcut }}</kbd>
    @endif
</{{ $el }}>
