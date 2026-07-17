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
    'inline-flex items-center justify-center min-w-5 px-1.5 py-0.5',
    'rounded border border-zinc-200 dark:border-zinc-700',
    'text-xs font-medium text-zinc-500 dark:text-zinc-400',
    'bg-white dark:bg-zinc-800',
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
