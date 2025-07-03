@props([
    'action' => null,
    'badge' => null,
    'badgeColor' => null,
    'href' => null,
    'type' => 'button',
    'newtab' => false,
    'target' => null,
    'variant' => null,
    'icon' => null,
    'iconSuffix' => null,
])

@php
$variant ??= in_array($action, ['delete', 'remove']) ? 'danger' : null;

$icon = [
    'start' => $icon ?? match ($action) {
        'edit' => 'edit',
        'create' => 'add',
        'delete', 'remove' => 'delete',
        'duplicate' => 'copy',
        default => null,
    },
    'end' => $iconSuffix,
];

$el = $href ? 'a' : 'button';

$classes = Arr::toCssClasses([
    'flex items-center gap-2 w-full py-2 px-3 rounded-md',
    'text-left text-zinc-800 dark:text-white',
    'focus:outline-none',
    'disabled:pointer-events-none disabled:cursor-default',
    '[:where([data-atom-menu]_&)]:my-1 first:[:where([data-atom-menu]_&)]:mt-0 last:[:where([data-atom-menu]_&)]:mb-0',
    $variant === 'danger'
        ? 'focus:bg-red-100 hover:text-red-500 hover:bg-red-100'
        : 'focus:bg-zinc-800/5 hover:bg-zinc-800/5 dark:hover:bg-zinc-600',
]);

$merges = [
    'href' => $el === 'button' ? null : $href,
    'type' => $el === 'button' ? $type : false,
    'target' => $el === 'button' ? false : ($target ?? ($href && $newtab ? '_blank' : false)),
    'data-atom-menu-item' => true,
];

if ($action === 'delete') {
    $merges = [
        ...$merges,
        'x-on:click' => 'Atom.confirm({ type: \'delete\' }).then(() => $dispatch(\'confirmed\'))',
        'x-on:confirmed' => '$wire.delete()',
    ];
}
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge($merges) }}>
    @if ($icon = data_get($icon, 'start'))
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0 opacity-40 size-5"/>
    @endif

    <div class="grow leading-tight whitespace-nowrap truncate">
        {{ $slot }}
    </div>

    @if ($badge)
        <atom:navlist.badge :color="$badgeColor" class="shrink-0">{{ $badge }}</atom:navlist.badge>
    @endif

    @if ($icon = data_get($icon, 'end'))
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0 opacity-40 group-hover/collapse:hidden"/>
    @endif
</{{ $el }}>