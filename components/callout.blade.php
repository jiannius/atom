@props([
    'variant' => null,
    'closeable' => false,
    'icon' => null,
    'title' => null,
    'text' => null,
])

@php
$icon ??= match ($variant) {
    'info' => 'info',
    'warning' => 'warning',
    'success' => 'check-circle',
    'danger', 'error' => 'error',
    default => null,
};

$classes = Arr::toCssClasses([
    'relative w-full rounded-lg border py-4 px-6 flex gap-3',
    match ($variant) {
        'info' => 'bg-sky-100 dark:bg-sky-900/20 border-sky-200 dark:border-sky-400/50 text-sky-600 dark:text-sky-100/80 [&>[data-atom-icon]]:text-sky-400',
        'success' => 'bg-green-100 dark:bg-green-900/20 border-green-200 dark:border-green-400/50 text-green-600 dark:text-green-100/80 [&>[data-atom-icon]]:text-green-400',
        'warning' => 'bg-yellow-100 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-400/50 text-yellow-600 dark:text-yellow-100/80 [&>[data-atom-icon]]:text-yellow-400',
        'danger', 'error' => 'bg-red-100 dark:bg-red-900/20 border-red-200 dark:border-red-400/50 text-red-600 dark:text-red-100/80 [&>[data-atom-icon]]:text-red-400',
        default => 'border-zinc-200 bg-zinc-100',
    },
]);
@endphp

<div x-data="{ show: true }" x-show="show" {{ $attributes->class($classes) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0" variant="solid" />
    @endif

    <div class="grow space-y-2">
        @if ($title)
            <div class="font-semibold">{{ t($title) }}</div>
        @endif

        @if ($text)
            <div>{{ t($text) }}</div>
        @endif
    </div>

    @if ($closeable)
        <button
        type="button"
        x-on:click.stop="show = false"
        class="absolute top-3 right-3 p-1 flex items-center justify-center">
            <atom:icon.close class="size-5"/>
        </button>
    @endif
</div>
