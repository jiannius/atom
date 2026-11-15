@props([
    'badge' => null,
    'badgeColor' => null,
    'href' => null,
    'type' => 'button',
    'newtab' => false,
    'target' => null,
    'variant' => 'default',  // default, danger, warning, delete, remove
    'passphrase' => null,   // text the user must type to confirm a delete
    'phrase' => null,   // alias of passphrase
    'icon' => null,
    'iconSuffix' => null,
    'confirm' => null,   // a heading, an array of dialog options, true for the default copy; false opts a delete out
])

@php
$icons = [
    'start' => $icon ?? (in_array($variant, ['delete', 'remove']) ? 'delete' : null),
    'end' => $iconSuffix,
];

$href = safe_url($href);
$passphrase = $passphrase ?: $phrase;

/* A link is never `:disabled`, so `disabled` on a link item dimmed nothing and still navigated. A disabled item is a real
   disabled button: no href, out of the tab order. */
$disabled = $attributes->has('disabled') && $attributes->get('disabled') !== false && $attributes->get('disabled') !== null;
$el = $href && ! $disabled ? 'a' : 'button';

$hasEnd = isset($end) && $end->hasActualContent();

/* A row of the control height (28px compact, 40 on touch) with a 6px radius. Hover and keyboard focus tint it; the focus ring
   is the base :focus-visible rule, drawn inside the row so the panel's padding does not clip it. A tone item reads the
   tone's text colour and tints with the tone's fill. */
$classes = Arr::toCssClasses([
    'flex items-center gap-2 w-full min-h-control py-1 px-2.5 rounded-nav',
    'text-left text-sm focus-visible:-outline-offset-2',
    'disabled:pointer-events-none disabled:cursor-default disabled:opacity-50',
    '[:where([data-atom-menu]_&)]:my-px first:[:where([data-atom-menu]_&)]:mt-0 last:[:where([data-atom-menu]_&)]:mb-0',
    match ($variant) {
        'danger', 'delete', 'remove' => 'text-danger-fg hover:bg-danger-bg focus-visible:bg-danger-bg',
        'warning' => 'text-warning-fg hover:bg-warning-bg focus-visible:bg-warning-bg',
        default => 'text-fg hover:bg-fg/[0.06] focus-visible:bg-fg/[0.06]',
    },
]);

$merges = [
    'href' => $el === 'button' ? null : $href,
    'type' => $el === 'button' ? $type : false,
    'target' => $el === 'button' ? false : ($target ?? ($href && $newtab ? '_blank' : false)),
    'role' => 'menuitem',
    'data-atom-menu-item' => true,
];

/* Asks before it acts when `confirm` says so, and a delete always does (`:confirm="false"` opts out); see button/index.blade.php. */
$handled = $attributes->wire('click')->value()
    || \Jiannius\Atom\Services\Attributes::hasLike($attributes, 'x-on:click*', '@click*');

$confirmation = \Jiannius\Atom\Services\ConfirmProp::resolve($confirm, $variant, $variant === 'delete', (bool) $handled, $el === 'a' || $type === 'submit', (string) $slot, $passphrase);

if (data_get($confirmation, 'mode') === 'handler') {
    $merges = [
        ...$merges,

        'x-on:click' => app('atom')->deleteConfirmHandler(
            data_get($confirmation, 'config.passphrase'),
            (string) data_get($confirmation, 'config.buttonConfirm', ''),
            $confirmation['given'],
        ),

        'x-on:confirmed' => "\$wire.delete()",
    ];
}
else if ($confirmation) {
    $merges = [...$merges, 'data-atom-confirm' => json_encode($confirmation['config'])];
}
else if ($variant === 'delete' && $confirm === false && !$handled && $el === 'button' && $type !== 'submit') {
    $merges = [...$merges, 'x-on:click' => "\$wire.delete()"];
}
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge($merges) }}>
    @if ($iconStart = data_get($icons, 'start'))
        <x-dynamic-component :component="'atom::icon.'.$iconStart" class="shrink-0 size-4 text-current/70"/>
    @endif

    <div class="grow leading-tight whitespace-nowrap truncate">
        {{ $slot }}
    </div>

    {{-- plain text at the row's end ("Coming soon", a shortcut): muted, small, and the badge and suffix icon follow it --}}
    @if ($hasEnd)
        <div {{ $end->attributes->class('shrink-0 ms-auto text-xs text-fg-subtle') }} data-atom-menu-item-end>{{ $end }}</div>
    @endif

    @if ($badge)
        <atom:navlist.badge :color="$badgeColor" class="shrink-0">{{ $badge }}</atom:navlist.badge>
    @endif

    @if ($iconEnd = data_get($icons, 'end'))
        <x-dynamic-component :component="'atom::icon.'.$iconEnd" class="shrink-0 size-4 text-current/70"/>
    @endif
</{{ $el }}>