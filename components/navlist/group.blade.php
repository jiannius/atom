@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
    'hiddenIfEmpty' => true,
])

@if ($hiddenIfEmpty && $slot->isEmpty())

@elseif ($expandable && $heading)

<div
x-data="{ open: @js($expanded === true) }"
{{ $attributes->class('group/disclosure') }}
data-atom-navlist-group>
    <button
    type="button"
    x-on:click="open = !open"
    x-bind:aria-expanded="open ? 'true' : 'false'"
    class="group/disclosure-button mb-[2px] flex h-10 w-full items-center rounded-lg text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 lg:h-8 dark:text-white/80 dark:hover:bg-white/[7%] dark:hover:text-white">
        <div class="ps-3 pe-4">
            <atom:icon.down x-show="open" class="size-3!" />
            <atom:icon.right x-show="!open" class="size-3!" />
        </div>

        <span class="text-sm font-medium leading-none">{{ t($heading) }}</span>
    </button>

    <div x-show="open" class="relative space-y-[2px] ps-7">
        <div class="absolute inset-y-[3px] start-0 ms-4 w-px bg-zinc-200 dark:bg-white/30"></div>
        {{ $slot }}
    </div>
</div>

@elseif ($heading)
    <div class="mt-5 first:mt-0">
        <div class="px-3 pb-1.5">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t($heading) }}</div>
        </div>

        <div {{ $attributes->class(['flex flex-col']) }}>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
