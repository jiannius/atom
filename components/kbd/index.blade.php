@props([
    'keys' => null,
])

@php
$map = [
    'cmd' => '⌘', 'command' => '⌘', 'meta' => '⌘', 'super' => '⌘', 'win' => '⊞',
    'shift' => '⇧',
    'alt' => '⌥', 'option' => '⌥', 'opt' => '⌥',
    'ctrl' => '⌃', 'control' => '⌃',
    'enter' => '⏎', 'return' => '⏎',
    'esc' => '⎋', 'escape' => '⎋',
    'tab' => '⇥',
    'backspace' => '⌫',
    'delete' => '⌦', 'del' => '⌦',
    'space' => '␣',
    'up' => '↑', 'down' => '↓', 'left' => '←', 'right' => '→',
    'plus' => '+',
];

$tokens = filled($keys)
    ? (is_array($keys) ? $keys : preg_split('/[\s+]+/', trim($keys), -1, PREG_SPLIT_NO_EMPTY))
    : null;

$render = function ($token) use ($map) {
    $key = strtolower((string) $token);

    return $map[$key]
        ?? (strlen($token) === 1 ? strtoupper($token) : ucfirst($token));
};

$cap = Arr::toCssClasses([
    'inline-flex h-5 min-w-5 items-center justify-center px-1.5',
    'rounded-nav border border-border-strong bg-surface-sunken',
    'text-xs font-medium text-fg-muted',
]);
@endphp

@if ($tokens)
    <span {{ $attributes->class('inline-flex items-center gap-1') }} data-atom-kbd>
        @foreach ($tokens as $token)
            <kbd class="{{ $cap }}" data-atom-kbd-key>{{ $render($token) }}</kbd>
        @endforeach
    </span>
@else
    <kbd {{ $attributes->class($cap) }} data-atom-kbd data-atom-kbd-key>{{ $slot }}</kbd>
@endif
