@props([
    'size' => null,
    'block' => false,
    'href' => null,
    'type' => null,
    'rel' => 'noopener noreferrer nofollow',
    'newtab' => false,
    'phrase' => '',
    'inverted' => null,
    'social' => null,
    'variant' => null,
    'icon' => null,
    'iconSuffix' => null,
])

@php
$inverted ??= $type === 'delete';

$variant ??= data_get($social, 'name') ?? match ($type) {
    'submit' => 'primary',
    'delete' => 'danger',
    default => 'default',
};

$icon = [
    'start' => $icon ?? data_get($social, 'name') ?? match ($type) {
        'submit' => 'check',
        'delete' => 'delete',
        default => null,
    },
    'end' => $iconSuffix,
    'class' => match ($size) {
        'lg' => 'size-10',
        'md' => 'size-6',
        'sm' => 'size-4',
        'xs' => 'size-3',
        default => 'size-5',
    },
];

$classes = [
    'group/button relative items-center justify-center',
    $block ? 'flex w-full' : 'inline-flex',
    'whitespace-nowrap font-medium transition-colors outline-offset-1',
    'disabled:pointer-events-none disabled:cursor-default disabled:opacity-50',
    'group-[]/buttons:-ml-[1px] group-[]/buttons:first:ml-0',
];

if ($variant === 'link') {
    $classes[] = 'bg-transparent text-zinc-800 border border-transparent underline-offset-5 decoration-dotted hover:underline focus:underline focus:outline-none';
}
else {
    $classes[] = 'focus:outline-1';

    if ($variant === 'ghost') {
        $classes[] = 'bg-transparent text-zinc-600 dark:text-zinc-400 border border-transparent focus:outline-zinc-200 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-zinc-300';
    }
    else if ($inverted) {
        $classes[] = 'inset-shadow-xs inset-shadow-zinc-100/30';

        $classes[] = match ($variant) {
            'primary' => 'bg-primary-foreground text-primary border border-transparent focus:outline-primary hover:bg-primary hover:text-primary-foreground',
            'accent' => 'bg-accent-foreground text-accent border border-transparent focus:outline-accent hover:bg-accent hover:text-accent-foreground',
            'warning' => 'bg-yellow-100 text-yellow-500 border border-transparent focus:outline-yellow-300 hover:bg-yellow-500 hover:text-yellow-800',
            'danger', 'error' => 'bg-red-100 text-red-500 border border-transparent focus:outline-red-300 hover:bg-red-500 hover:text-red-100',
            'facebook' => 'bg-blue-100 text-blue-600 border border-transparent focus:outline-blue-300 hover:bg-blue-600 hover:text-blue-100',
            'google' => 'bg-rose-100 text-rose-600 border border-transparent focus:outline-rose-300 hover:bg-rose-600 hover:text-rose-100',
            'linkedin' => 'bg-sky-100 text-sky-600 border border-transparent focus:outline-sky-300 hover:bg-sky-600 hover:text-sky-100',
            'whatsapp' => 'bg-green-100 text-green-600 border border-transparent focus:outline-green-300 hover:bg-green-600 hover:text-sky-100',
            'telegram' => 'bg-sky-100 text-sky-600 border border-transparent focus:outline-sky-300 hover:bg-sky-600 hover:text-sky-100',
            default => 'bg-zinc-100 text-zinc-500 border border-transparent focus:outline-zinc-200 hover:bg-white hover:text-zinc-800 hover:border-zinc-200',
        };
    }
    else {
        $classes[] = 'inset-shadow-zinc-100/30 hover:opacity-90';

        $classes[] = match ($variant) {
            'primary' => 'bg-primary text-primary-foreground border border-transparent focus:outline-primary inset-shadow-xs',
            'accent' => 'bg-accent text-accent-foreground border border-transparent focus:outline-accent inset-shadow-xs',
            'warning' => 'bg-yellow-500 text-yellow-800 border border-transparent focus:outline-yellow-500 inset-shadow-xs',
            'danger', 'error' => 'bg-red-500 text-red-100 border border-transparent focus:outline-red-500 inset-shadow-xs',
            'facebook' => 'bg-blue-600 text-blue-100 border border-transparent focus:outline-blue-700 inset-shadow-xs',
            'google' => 'bg-rose-600 text-rose-100 border border-transparent focus:outline-rose-700 inset-shadow-xs',
            'linkedin' => 'bg-sky-600 text-sky-100 border border-transparent focus:outline-sky-700 inset-shadow-xs',
            'whatsapp' => 'bg-green-600 text-green-100 border border-transparent focus:outline-green-700 inset-shadow-xs',
            'telegram' => 'bg-sky-600 text-sky-100 border border-transparent focus:outline-sky-700 inset-shadow-xs',
            default => 'bg-white text-zinc-800 border border-zinc-200 hover:bg-zinc-100/30 dark:hover:bg-zinc-50 focus:outline-zinc-200 shadow-sm',
        };
    }
}

if ($slot->isEmpty() && data_get($icon, 'start')) {
    $classes[] = match ($size) {
        'lg' => 'size-12 rounded-lg',
        'sm' => 'size-8 rounded-md',
        'xs' => 'size-6 rounded-md',
        default => 'size-10 rounded-lg',
    };
}
else {
    $classes[] = match ($size) {
        'lg' => 'text-lg h-14 px-5 gap-2 rounded-lg',
        'md' => 'text-base h-12 px-4 gap-2 rounded-lg',
        'sm' => 'text-sm h-8 px-3 gap-1 rounded-md',
        'xs' => 'text-xs h-6 px-2 gap-1 rounded-md',
        default => 'h-10 px-4 gap-2 rounded-lg',
    };
}

$classes = Arr::toCssClasses($classes);

if (
    $href
    || (
        in_array(data_get($social, 'name'), ['google', 'facebook', 'linkedin'])
        && ($href = route('socialite.redirect', ['provider' => data_get($social, 'name'), ...request()->query()]))
    )
    || (
        data_get($social, 'name') === 'whatsapp'
        && ($href = 'https://wa.me/'.data_get($social, 'number').'?text='.data_get($social, 'text'))
    )
    || (
        data_get($social, 'name') === 'telegram'
        && ($href = 'https://t.me/share/url?url='.data_get($social, 'url').'&text='.data_get($social, 'text'))
    )
) {
    $el = 'a';
    $merges = [
        'href' => $href,
        'rel' => $rel,
        'target' => $newtab ? '_blank' : null,
    ];
}
else {
    $el = 'button';
    $merges = ['type' => $type === 'submit' ? 'submit' : 'button'];
}

if (!$attributes->wire('loading')->value() && $attributes->wire('click')->value()) {
    $merges = [
        ...$merges,
        'wire:loading.class' => 'opacity-50 pointer-events-none is-loading',
    ];

    if (!$attributes->wire('target')->value()) {
        $merges = [
            ...$merges,
            'wire:target' => $attributes->wire('click')->value(),
        ];
    }
}

if ($type === 'delete' && !$attributes->wire('click')->value() && !$attributes->has('x-on:click')) {
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

if ($slot->isNotEmpty()) {
    $merges = [
        ...$merges,
        'aria-label' => strip_tags($slot->toHtml()),
    ];
}
@endphp

<{{ $el }} {{ $attributes->merge($merges)->class($classes) }}>
    <div class="absolute inset-0 items-center justify-center hidden group-[.is-loading]/button:flex">
        <atom:icon.loading :class="data_get($icon, 'class')"/>
    </div>

    <div class="inline-flex items-center justify-center gap-2 group-[.is-loading]/button:opacity-0">
        @if (data_get($icon, 'start'))
            <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'start')" class="shrink-0 {{ data_get($icon, 'class') }}"/>
        @endif

        {{ $slot }}

        @if (data_get($icon, 'end'))
            <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'end')" class="shrink-0 -ml-0.5 {{ data_get($icon, 'class') }}"/>
        @endif
    </div>
</{{ $el }}>
