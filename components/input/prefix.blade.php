@props([
    'prefix' => null,
    'suffix' => null,
])

@php
$class = 'h-10 px-3 border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-700 shadow-sm text-muted dark:text-muted-foreground flex items-center justify-center';
@endphp

<div @class([
    'flex items-center w-full',
    '[&_[data-atom-input]_input]:rounded-l-none' => !empty($prefix),
    '[&_[data-atom-input]_input]:rounded-r-none' => !empty($suffix),
])>
    @if ($prefix)
        <div @class([$class, 'rounded-l-lg border-r-0'])>
            {{ t($prefix) }}
        </div>
    @endif

    <div class="grow">
        {{ $slot }}
    </div>

    @if ($suffix)
        <div @class([$class, 'rounded-r-lg border-l-0 -ml-px'])>
            {{ t($suffix) }}
        </div>
    @endif
</div>
