@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
])

@php
$el = $href ? 'a' : 'button';
$label = trim(strip_tags($slot->toHtml()));
$classes = Arr::toCssClasses([
    'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-start text-sm',
    'text-zinc-700 dark:text-zinc-200',
    'hover:bg-zinc-100 dark:hover:bg-zinc-800',
    'data-active:bg-zinc-100 dark:data-active:bg-zinc-800',
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
        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0 text-zinc-400"/>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($shortcut)
        <kbd class="shrink-0 rounded border border-zinc-200 px-1.5 py-0.5 text-xs text-zinc-400 dark:border-zinc-700">{{ $shortcut }}</kbd>
    @endif
</{{ $el }}>
