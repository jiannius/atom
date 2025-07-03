@php
$classes = Arr::toCssClasses([
    '[:where(&)]:min-w-48 p-[.3125rem]',
    'rounded-lg shadow-xs',
    'border border-zinc-200 dark:border-zinc-600',
    'bg-white dark:bg-zinc-700',
    'focus:outline-hidden',
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-menu>
    {{ $slot }}
</div>
