@props([
    'badge' => null,
    'badgeColor' => null,
    'href' => null,
    'type' => 'button',
    'newtab' => false,
    'target' => null,
    'variant' => 'default',  // default, danger, warning, delete, remove
    'phrase' => null,   // phrase to input when delete
    'icon' => null,
    'iconSuffix' => null,
])

@php
$icons = [
    'start' => $icon ?? (in_array($variant, ['delete', 'remove']) ? 'delete' : null),
    'end' => $iconSuffix,
];

$el = $href ? 'a' : 'button';

$classes = Arr::toCssClasses([
    'flex items-center gap-2 w-full py-2 px-3 rounded-md',
    'text-left text-zinc-800 dark:text-white',
    'focus:outline-none',
    'disabled:pointer-events-none disabled:cursor-default',
    '[:where([data-atom-menu]_&)]:my-1 first:[:where([data-atom-menu]_&)]:mt-0 last:[:where([data-atom-menu]_&)]:mb-0',
    match ($variant) {
        'danger', 'delete', 'remove' => 'focus:bg-red-100 hover:text-red-500 hover:bg-red-100',
        'warning' => 'focus:bg-yellow-100 hover:text-yellow-500 hover:bg-yellow-100',
        default => 'focus:bg-zinc-800/5 dark:focus:bg-zinc-700 hover:bg-zinc-800/5 dark:hover:bg-zinc-600',
    },
]);

$merges = [
    'href' => $el === 'button' ? null : $href,
    'type' => $el === 'button' ? $type : false,
    'target' => $el === 'button' ? false : ($target ?? ($href && $newtab ? '_blank' : false)),
    'role' => 'menuitem',
    'data-atom-menu-item' => true,
];

if ($variant === 'delete' && !$attributes->wire('click')->value() && !$attributes->has('x-on:click')) {
    $merges = [
        ...$merges,

        'x-on:click' => "atom.confirm({
            variant: 'danger',
            heading: '".t('atom::messages.permanently-delete-record')."',
            message: '".t('atom::messages.are-you-sure-to-delete-this-record')."',
            phrase: '$phrase',
        }).then(() => \$dispatch('confirmed')).catch(() => {})",

        'x-on:confirmed' => "\$wire.delete()",
    ];
}
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge($merges) }}>
    @if ($iconStart = data_get($icons, 'start'))
        <x-dynamic-component :component="'atom::icon.'.$iconStart" class="shrink-0 opacity-40 size-5"/>
    @endif

    <div class="grow leading-tight whitespace-nowrap truncate">
        {{ $slot }}
    </div>

    @if ($badge)
        <atom:navlist.badge :color="$badgeColor" class="shrink-0">{{ $badge }}</atom:navlist.badge>
    @endif

    @if ($iconEnd = data_get($icons, 'end'))
        <x-dynamic-component :component="'atom::icon.'.$iconEnd" class="shrink-0 opacity-40 size-5"/>
    @endif
</{{ $el }}>