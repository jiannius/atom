@props([
    'size' => null,
    'block' => false,
    'href' => null,
    'type' => null,
    'rel' => 'noopener noreferrer',
    'newtab' => false,
    'passphrase' => '',
    'inverted' => null,
    'social' => null,
    'variant' => null,
    'color' => null,
    'icon' => null,
    'iconSuffix' => null,
])

@php
$href = safe_url($href);

$inverted ??= $type === 'delete';

$variant ??= data_get($social, 'name') ?? match ($type) {
    'submit' => 'primary',
    'delete' => 'danger',
    default => 'default',
};

/* An icon is only ever the caller's (or a social button's own brand mark): type="submit" and type="delete" add none. */
$icon = [
    'start' => $icon ?? data_get($social, 'name'),
    'end' => $iconSuffix,
    'class' => match ($size) {
        'lg' => 'size-5',
        'xs' => 'size-3.5',
        default => 'size-4',
    },
];

/* The tier is the variant: `primary` is the solid accent (one per view), the default is bordered, `ghost` has no
   border or fill until hover, `link` is text. The tones (`danger`, `warning`, `success`, `info`) are solid fills of the
   role token's text colour, soft (the tone's own tint) when `inverted`. Written out in full, never built from a variable,
   so Tailwind finds each class. `accent` is the old name of `primary`; the social variants are the default tier with the
   brand's icon. */
$tone = match ($variant) {
    'primary', 'accent' => 'accent',
    'danger', 'error' => 'danger',
    'warning' => 'warning',
    'success' => 'success',
    'info' => 'info',
    default => null,
};

$classes = [
    'group/button relative items-center justify-center overflow-hidden',
    $block ? 'flex w-full' : 'inline-flex',
    'whitespace-nowrap font-medium transition-colors',
    'disabled:pointer-events-none disabled:cursor-default disabled:opacity-50',
    '[&.is-loading]:opacity-50 [&.is-loading]:pointer-events-none',
    // A submit button also reflects its parent <atom:form>'s loading state
    // (the form carries .is-loading while its wire:submit method runs), so it
    // spins regardless of the method name. No-op outside an atom:form.
    'group-[.is-loading]/form:opacity-50 group-[.is-loading]/form:pointer-events-none' => $type === 'submit',
];

if ($variant === 'link') {
    $classes[] = 'bg-transparent text-fg border border-transparent underline-offset-4 decoration-dotted hover:underline focus-visible:underline';
}
else if ($variant === 'ghost') {
    $classes[] = 'bg-transparent border border-transparent';
    $classes[] = match ($color) {
        'primary', 'accent' => 'text-accent-ink hover:bg-accent-soft',
        'warning' => 'text-warning-fg hover:bg-warning-bg',
        'danger', 'error' => 'text-danger-fg hover:bg-danger-bg',
        'success' => 'text-success-fg hover:bg-success-bg',
        'info' => 'text-info-fg hover:bg-info-bg',
        default => 'text-fg-muted hover:bg-fg/5 hover:text-fg',
    };
}
else if ($inverted && $tone) {
    $classes[] = 'border border-transparent';
    $classes[] = match ($tone) {
        'accent' => 'bg-accent-soft text-accent-ink hover:bg-accent hover:text-accent-foreground',
        'warning' => 'bg-warning-bg text-warning-fg hover:bg-warning-fg hover:text-surface',
        'success' => 'bg-success-bg text-success-fg hover:bg-success-fg hover:text-surface',
        'info' => 'bg-info-bg text-info-fg hover:bg-info-fg hover:text-surface',
        default => 'bg-danger-bg text-danger-fg hover:bg-danger-fg hover:text-surface',
    };
}
else if ($tone) {
    $classes[] = 'border border-transparent';
    $classes[] = match ($tone) {
        'accent' => 'bg-accent text-accent-foreground hover:bg-accent-hover',
        'warning' => 'bg-warning-fg text-surface hover:bg-warning-fg/90',
        'success' => 'bg-success-fg text-surface hover:bg-success-fg/90',
        'info' => 'bg-info-fg text-surface hover:bg-info-fg/90',
        default => 'bg-danger-fg text-surface hover:bg-danger-fg/90',
    };
}
else {
    $classes[] = 'border border-border-strong bg-surface text-fg hover:bg-surface-sunken';
}

/* Heights come from the control token (28px compact, 32 comfortable, 40 on a touch device): `sm` and `xs` sit 4px and 8px
   under it, `md` and `lg` 4px and 8px over. A slotless button with an icon is a square of the same height. */
$height = match ($size) {
    'lg' => 'h-control-lg',
    'md' => 'h-control-md',
    'sm' => 'h-control-sm',
    'xs' => 'h-control-xs',
    default => 'h-control',
};

if ($slot->isEmpty() && data_get($icon, 'start')) {
    $classes[] = $height.' aspect-square '.($size === 'xs' ? 'rounded-nav' : 'rounded-control');
}
else {
    $classes[] = match ($size) {
        'lg' => 'text-lg h-control-lg px-4 gap-2 rounded-control',
        'md' => 'text-base h-control-md px-3.5 gap-2 rounded-control',
        'sm' => 'text-sm h-control-sm px-2.5 gap-1.5 rounded-control',
        'xs' => 'text-xs h-control-xs px-2 gap-1 rounded-nav',
        default => 'text-sm h-control px-3 gap-1.5 rounded-control',
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

if (!$attributes->wire('loading')->value()) {
    if ($attributes->wire('click')->value()) {
        $merges = [...$merges, 'wire:loading.class' => 'is-loading'];

        if (!$attributes->wire('target')->value()) {
            $merges = [...$merges, 'wire:target' => $attributes->wire('click')->value()];
        }
    }
    else if ($type === 'submit') {
        $merges = [...$merges, 'wire:loading.class' => 'is-loading', 'wire:target' => 'submit'];
    }
}

if ($type === 'delete' && !$attributes->wire('click')->value() && !$attributes->has('x-on:click')) {
    $merges = [
        ...$merges,

        'x-on:click' => "atom.confirm({
            variant: 'danger',
            heading: '".t('atom::messages.permanently-delete-record')."',
            message: '".t('atom::messages.are-you-sure-to-delete-this-record')."',
            passphrase: '$passphrase',
        }).then(() => \$dispatch('confirmed')).catch(() => {})",

        'x-on:confirmed' => "\$wire.delete()",
    ];
}

if ($slot->isEmpty() && data_get($icon, 'start')) {
    $merges = [
        ...$merges,
        'aria-label' => str(data_get($icon, 'start'))->headline()->toString(),
    ];
}
@endphp

<{{ $el }} {{ $attributes->merge($merges)->class($classes) }} data-atom-button>
    <div class="absolute inset-0 items-center justify-center hidden group-[.is-loading]/button:flex {{ $type === 'submit' ? 'group-[.is-loading]/form:flex' : '' }}">
        <atom:icon.loading :class="data_get($icon, 'class')"/>
    </div>

    <div class="inline-flex items-center justify-center gap-2 group-[.is-loading]/button:opacity-0 {{ $type === 'submit' ? 'group-[.is-loading]/form:opacity-0' : '' }}">
        @if (data_get($icon, 'start'))
            <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'start')" class="shrink-0 {{ data_get($icon, 'class') }}"/>
        @endif

        {{ $slot }}

        @if (data_get($icon, 'end'))
            <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'end')" class="shrink-0 -ml-0.5 {{ data_get($icon, 'class') }}"/>
        @endif
    </div>
</{{ $el }}>
